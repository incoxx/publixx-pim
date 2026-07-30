<?php

declare(strict_types=1);

namespace App\Services\PimSync;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\ValueListEntry;
use App\Support\Media\MediaFileTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PimSyncImportService
{
    /**
     * Ergebnis-Statistiken des letzten Imports.
     */
    private array $stats = [
        'created'  => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'errors'   => 0,
        'details'  => [],
    ];

    /**
     * Cache für Attribut-Lookup (technical_name → Attribute).
     * @var array<string, Attribute|null>
     */
    private array $attributeCache = [];

    /**
     * Produkte aus dem Transfer-Format importieren/upserten.
     *
     * @param  array   $products  Array von Produkten im Transfer-Format
     * @param  array{
     *   conflict_resolution?: string,
     *   sync_attributes?: bool,
     *   sync_prices?: bool,
     *   sync_media?: bool,
     * } $options
     * @return array Import-Statistiken
     */
    public function importProducts(array $products, array $options = []): array
    {
        $this->stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $conflictResolution = $options['conflict_resolution'] ?? 'remote_wins';
        $syncAttributes = $options['sync_attributes'] ?? true;
        $syncPrices = $options['sync_prices'] ?? true;
        $syncMedia = $options['sync_media'] ?? true;

        foreach ($products as $productData) {
            try {
                $this->importSingleProduct($productData, $conflictResolution, $syncAttributes, $syncPrices, $syncMedia);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->stats['details'][] = [
                    'sku'   => $productData['sku'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                Log::channel('connectors')->error('PimSync Import fehlgeschlagen', [
                    'sku'   => $productData['sku'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->stats;
    }

    /**
     * Einzelnes Produkt importieren/upserten.
     */
    private function importSingleProduct(
        array $data,
        string $conflictResolution,
        bool $syncAttributes,
        bool $syncPrices,
        bool $syncMedia,
    ): void {
        $sku = $data['sku'] ?? null;
        if (! $sku) {
            throw new \InvalidArgumentException('Produkt ohne SKU kann nicht importiert werden.');
        }

        $existing = Product::where('sku', $sku)->first();

        // Conflict Resolution
        if ($existing && $conflictResolution === 'local_wins') {
            $this->stats['skipped']++;
            return;
        }

        if ($existing && $conflictResolution === 'newer_wins') {
            $remoteUpdated = $data['_updated_at'] ?? null;
            if ($remoteUpdated && $existing->updated_at->gte(new \DateTimeImmutable($remoteUpdated))) {
                $this->stats['skipped']++;
                return;
            }
        }

        DB::transaction(function () use ($data, $existing, $syncAttributes, $syncPrices, $syncMedia) {
            $product = $this->upsertProduct($data, $existing);

            if ($syncAttributes && ! empty($data['attributes'])) {
                $this->syncAttributes($product, $data['attributes']);
            }

            if ($syncPrices && ! empty($data['prices'])) {
                $this->syncPrices($product, $data['prices']);
            }

            if ($syncMedia && ! empty($data['media'])) {
                $this->syncMedia($product, $data['media']);
            }

            if ($existing) {
                $this->stats['updated']++;
            } else {
                $this->stats['created']++;
            }
        });
    }

    /**
     * Produkt anlegen oder aktualisieren.
     */
    private function upsertProduct(array $data, ?Product $existing): Product
    {
        $productTypeId = null;
        if (! empty($data['product_type'])) {
            $productType = ProductType::where('technical_name', $data['product_type'])->first();
            $productTypeId = $productType?->id;
        }

        $attributes = [
            'sku'             => $data['sku'],
            'ean'             => $data['ean'] ?? null,
            'name'            => $data['name'] ?? $data['sku'],
            'status'          => $data['status'] ?? 'draft',
            'product_type_id' => $productTypeId ?? $existing?->product_type_id,
        ];

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return Product::create(array_merge($attributes, [
            'product_type_ref' => 'product',
        ]));
    }

    /**
     * Attributwerte synchronisieren.
     * Fehlende Attribute werden automatisch angelegt (source_system = 'anypim-sync').
     */
    private function syncAttributes(Product $product, array $attributesData): void
    {
        foreach ($attributesData as $technicalName => $value) {
            $attribute = $this->resolveAttribute($technicalName, $value);
            if (! $attribute) {
                continue;
            }

            if ($attribute->is_translatable && is_array($value) && ! isset($value['value'])) {
                // Multi-language: {"de": "Wert", "fr": "Valeur"}
                foreach ($value as $language => $langValue) {
                    if (! is_string($language) || strlen($language) > 5) {
                        continue; // Überspringe nicht-Sprach-Keys
                    }
                    $this->upsertAttributeValue($product, $attribute, $langValue, $language);
                }
            } elseif (is_array($value) && isset($value['value'])) {
                // Unit-Value: {"value": 1.8, "unit": "kg"}
                $this->upsertAttributeValue($product, $attribute, $value['value'], null, $value['unit'] ?? null);
            } else {
                // Einfacher Wert
                $this->upsertAttributeValue($product, $attribute, $value);
            }
        }
    }

    /**
     * Attribut per technical_name auflösen. Bei Bedarf automatisch anlegen.
     */
    private function resolveAttribute(string $technicalName, mixed $value): ?Attribute
    {
        if (isset($this->attributeCache[$technicalName])) {
            return $this->attributeCache[$technicalName];
        }

        $attribute = Attribute::where('technical_name', $technicalName)->first();

        if (! $attribute) {
            // Auto-Anlage: Attribut existiert nicht lokal → erstellen
            $dataType = $this->inferDataType($value);
            $isTranslatable = is_array($value) && ! isset($value['value']);

            $attribute = Attribute::create([
                'technical_name'  => $technicalName,
                'name_de'         => Str::title(str_replace(['-', '_'], ' ', $technicalName)),
                'name_en'         => Str::title(str_replace(['-', '_'], ' ', $technicalName)),
                'data_type'       => $dataType,
                'is_translatable' => $isTranslatable,
                'source_system'   => 'anypim-sync',
                'status'          => 'active',
            ]);

            Log::channel('connectors')->info("PimSync: Attribut automatisch angelegt", [
                'technical_name' => $technicalName,
                'data_type'      => $dataType,
            ]);
        }

        $this->attributeCache[$technicalName] = $attribute;
        return $attribute;
    }

    /**
     * Datentyp aus dem Wert ableiten.
     */
    private function inferDataType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_array($value)) {
            if (isset($value['value']) && (is_int($value['value']) || is_float($value['value']))) {
                return 'number';
            }
            // Multi-language → text
            return 'text';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return 'date';
        }

        return 'text';
    }

    /**
     * Einzelnen Attributwert upserten.
     */
    private function upsertAttributeValue(
        Product $product,
        Attribute $attribute,
        mixed $value,
        ?string $language = null,
        ?string $unitSymbol = null,
    ): void {
        $conditions = [
            'product_id'   => $product->id,
            'attribute_id' => $attribute->id,
            'language'     => $language,
        ];

        $fields = $this->buildValueFields($value, $attribute->data_type);

        // Unit auflösen
        if ($unitSymbol) {
            $unit = Unit::where(fn ($q) => $q->where('symbol', $unitSymbol)->orWhere('abbreviation', $unitSymbol))
                ->first();
            if ($unit) {
                $fields['unit_id'] = $unit->id;
            }
        }

        ProductAttributeValue::updateOrCreate($conditions, $fields);
    }

    /**
     * Wert-Felder für ProductAttributeValue bestimmen.
     */
    private function buildValueFields(mixed $value, string $dataType): array
    {
        $fields = [
            'value_string'       => null,
            'value_number'       => null,
            'value_date'         => null,
            'value_flag'         => null,
            'value_selection_id' => null,
        ];

        if ($value === null) {
            return $fields;
        }

        switch ($dataType) {
            case 'boolean':
                $fields['value_flag'] = (bool) $value;
                break;
            case 'number':
                $fields['value_number'] = is_numeric($value) ? (float) $value : null;
                break;
            case 'date':
                $fields['value_date'] = $value;
                break;
            default:
                if (is_string($value)) {
                    $fields['value_string'] = $value;
                } elseif (is_numeric($value)) {
                    $fields['value_number'] = (float) $value;
                } else {
                    $fields['value_string'] = (string) $value;
                }
                break;
        }

        return $fields;
    }

    /**
     * Preise synchronisieren.
     */
    private function syncPrices(Product $product, array $pricesData): void
    {
        foreach ($pricesData as $priceData) {
            $priceType = PriceType::where('technical_name', $priceData['type'] ?? '')->first();
            if (! $priceType) {
                continue;
            }

            ProductPrice::updateOrCreate(
                [
                    'product_id'    => $product->id,
                    'price_type_id' => $priceType->id,
                    'currency'      => $priceData['currency'] ?? 'EUR',
                ],
                [
                    'amount'     => $priceData['amount'] ?? 0,
                    'valid_from' => $priceData['valid_from'] ?? null,
                    'valid_to'   => $priceData['valid_to'] ?? null,
                    'scale_from' => $priceData['scale_from'] ?? null,
                    'scale_to'   => $priceData['scale_to'] ?? null,
                ],
            );
        }
    }

    /**
     * Media-Datei von einer Remote-Instanz empfangen und lokal speichern
     * (Gegenstück zu AnyPimMediaService::pushMedia()). Anders als syncMedia()
     * (Produkt-Zuordnungen, konservativ — überschreibt bestehende Metadaten nicht)
     * ist dies eine gezielte Push-Aktion: die übertragene Datei/Metadaten gewinnen.
     *
     * @param  array{file_name: string, mime_type?: string|null, title_de?: string|null,
     *   title_en?: string|null, alt_text_de?: string|null, alt_text_en?: string|null}  $meta
     */
    public function receiveMediaFile(UploadedFile $file, array $meta): Media
    {
        $fileName = $meta['file_name'];
        $filePath = 'media/' . $fileName;
        $disk = Storage::disk('public');

        $disk->putFileAs('media', $file, $fileName);

        $mimeType = $meta['mime_type'] ?? $file->getMimeType() ?? 'application/octet-stream';

        return Media::updateOrCreate(
            ['file_name' => $fileName],
            [
                'original_file_name' => $fileName,
                'file_path'          => $filePath,
                'mime_type'          => $mimeType,
                'file_size'          => $disk->size($filePath),
                'media_type'         => MediaFileTypes::classifyMimeType($mimeType),
                'title_de'           => $meta['title_de'] ?? null,
                'title_en'           => $meta['title_en'] ?? null,
                'alt_text_de'        => $meta['alt_text_de'] ?? null,
                'alt_text_en'        => $meta['alt_text_en'] ?? null,
            ],
        );
    }

    /**
     * Media-Zuordnungen synchronisieren (Referenz-Modus: nur Metadaten speichern).
     */
    private function syncMedia(Product $product, array $mediaData): void
    {
        foreach ($mediaData as $mediaItem) {
            $fileName = $mediaItem['file_name'] ?? null;
            if (! $fileName) {
                continue;
            }

            // Media-Datensatz finden oder erstellen
            $media = Media::where('file_name', $fileName)->first();
            if (! $media) {
                $filePath = $mediaItem['file_path'] ?? 'media/' . $fileName;
                // file_size ist NOT NULL ohne Default — die eigentliche Datei kommt hier (noch)
                // nicht mit (nur Metadaten), daher 0 wenn sie lokal nicht bereits vorhanden ist.
                // "Fehlende Medien synchronisieren" im anyPIM-Connector kann sie später nachladen.
                $disk = \Illuminate\Support\Facades\Storage::disk('public');
                $fileSize = $disk->exists($filePath) ? $disk->size($filePath) : 0;

                $media = Media::create([
                    'file_name'          => $fileName,
                    'original_file_name' => $fileName,
                    'file_path'          => $filePath,
                    'mime_type'          => $mediaItem['mime_type'] ?? 'application/octet-stream',
                    'file_size'          => $fileSize,
                    'media_type'         => 'image',
                    'title_de'           => $mediaItem['title_de'] ?? null,
                    'title_en'           => $mediaItem['title_en'] ?? null,
                    'alt_text_de'        => $mediaItem['alt_text_de'] ?? null,
                    'alt_text_en'        => $mediaItem['alt_text_en'] ?? null,
                ]);
            }

            // UsageType auflösen
            $usageTypeId = null;
            if (! empty($mediaItem['usage_type'])) {
                $usageType = MediaUsageType::where('technical_name', $mediaItem['usage_type'])->first();
                $usageTypeId = $usageType?->id;
            }

            // Zuordnung upserten
            ProductMediaAssignment::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'media_id'   => $media->id,
                ],
                [
                    'usage_type_id' => $usageTypeId,
                    'is_primary'    => $mediaItem['is_primary'] ?? false,
                    'sort_order'    => $mediaItem['sort_order'] ?? 0,
                ],
            );
        }
    }
}
