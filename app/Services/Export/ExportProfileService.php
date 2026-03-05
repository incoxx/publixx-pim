<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\ExportProfile;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductMediaAssignment;
use App\Services\Export\Writers\CsvWriter;
use App\Services\Export\Writers\ExcelWriter;
use App\Services\Export\Writers\JsonWriter;
use App\Services\Export\Writers\XmlWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestriert den Export basierend auf einem ExportProfile.
 *
 * 1. Lädt das verknüpfte SearchProfile → führt die Suche aus → Produkt-IDs
 * 2. Baut die Exportdaten zusammen
 * 3. Delegiert an den richtigen Kanal-Writer
 */
class ExportProfileService
{
    /**
     * Führt einen Export basierend auf dem Profil aus.
     */
    public function execute(ExportProfile $profile, ?string $fileName = null): StreamedResponse|JsonResponse
    {
        $products = $this->resolveProducts($profile);
        $data = $this->buildExportData($profile, $products);

        $resolvedFileName = $this->resolveFileName($profile, $fileName);

        return match ($profile->format) {
            'csv' => (new CsvWriter())->write($data, $resolvedFileName),
            'json' => (new JsonWriter())->write($data, $resolvedFileName),
            'xml' => (new XmlWriter())->write($data, $resolvedFileName),
            default => (new ExcelWriter())->write($data, $resolvedFileName),
        };
    }

    /**
     * Gibt die Anzahl der Produkte zurück, die der Export treffen würde.
     */
    public function count(ExportProfile $profile): int
    {
        return $this->buildProductQuery($profile)->count();
    }

    /**
     * Baut die Produkt-Query basierend auf dem SearchProfile.
     */
    private function buildProductQuery(ExportProfile $profile): Builder
    {
        $query = Product::query()->where('product_type_ref', 'product');

        $searchProfile = $profile->searchProfile;
        if (!$searchProfile) {
            return $query;
        }

        if ($searchProfile->status_filter) {
            $query->where('status', $searchProfile->status_filter);
        }

        if ($searchProfile->search_text) {
            $term = $searchProfile->search_text;
            $like = '%' . $term . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'LIKE', $like)
                  ->orWhere('sku', 'LIKE', $like)
                  ->orWhere('ean', 'LIKE', $like);
            });
        }

        if (!empty($searchProfile->category_ids)) {
            $query->whereIn('master_hierarchy_node_id', $searchProfile->category_ids);
        }

        return $query;
    }

    private function resolveProducts(ExportProfile $profile): \Illuminate\Support\Collection
    {
        $query = $this->buildProductQuery($profile);

        $relations = ['productType'];

        if ($profile->include_variants) {
            $relations[] = 'variants';
        }

        return $query->with($relations)->get();
    }

    /**
     * Baut die Exportdaten basierend auf den Profil-Einstellungen.
     */
    private function buildExportData(ExportProfile $profile, \Illuminate\Support\Collection $products): array
    {
        $data = ['products' => []];
        $productIds = $products->pluck('id')->toArray();
        $languages = $profile->languages ?? ['de'];

        // Attribute laden
        $attributeValues = [];
        if ($profile->include_attributes) {
            $attrQuery = ProductAttributeValue::whereIn('product_id', $productIds);
            if (!empty($profile->attribute_ids)) {
                $attrQuery->whereIn('attribute_id', $profile->attribute_ids);
            }
            $attributeValues = $attrQuery->get()->groupBy('product_id');
        }

        // Preise laden
        $prices = [];
        if ($profile->include_prices) {
            $prices = ProductPrice::whereIn('product_id', $productIds)
                ->get()
                ->groupBy('product_id');
        }

        // Beziehungen laden
        $relations = [];
        if ($profile->include_relations) {
            $relations = ProductRelation::whereIn('source_product_id', $productIds)
                ->get()
                ->groupBy('source_product_id');
        }

        // Medien laden
        $media = [];
        if ($profile->include_media) {
            $media = ProductMediaAssignment::whereIn('product_id', $productIds)
                ->with('media')
                ->get()
                ->groupBy('product_id');
        }

        // Attribute-Definitionen laden
        $attributes = [];
        if ($profile->include_attributes) {
            $attrQuery = Attribute::query();
            if (!empty($profile->attribute_ids)) {
                $attrQuery->whereIn('id', $profile->attribute_ids);
            }
            $attributes = $attrQuery->get()->keyBy('id');
        }

        foreach ($products as $product) {
            $productData = [
                'sku' => $product->sku,
                'name' => $product->name,
                'status' => $product->status,
                'ean' => $product->ean,
                'product_type' => $product->productType?->name_de,
            ];

            if ($profile->include_attributes && isset($attributeValues[$product->id])) {
                $productData['attributes'] = [];
                foreach ($attributeValues[$product->id] as $attrValue) {
                    if (!in_array($attrValue->language ?: 'de', $languages)) {
                        continue;
                    }
                    $attrDef = $attributes[$attrValue->attribute_id] ?? null;
                    $productData['attributes'][] = [
                        'attribute' => $attrDef?->technical_name ?? $attrValue->attribute_id,
                        'attribute_name' => $attrDef?->name_de ?? '',
                        'value' => $attrValue->value_string ?? $attrValue->value_number ?? $attrValue->value_date ?? $attrValue->value_flag,
                        'language' => $attrValue->language,
                    ];
                }
            }

            if ($profile->include_prices && isset($prices[$product->id])) {
                $productData['prices'] = $prices[$product->id]->map(fn($p) => [
                    'price_type' => $p->price_type,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'scale_from' => $p->scale_from,
                    'valid_from' => $p->valid_from,
                    'valid_to' => $p->valid_to,
                ])->toArray();
            }

            if ($profile->include_relations && isset($relations[$product->id])) {
                $productData['relations'] = $relations[$product->id]->map(fn($r) => [
                    'target_sku' => $r->targetProduct?->sku,
                    'relation_type' => $r->relationType?->name,
                    'position' => $r->position,
                ])->toArray();
            }

            if ($profile->include_media && isset($media[$product->id])) {
                $productData['media'] = $media[$product->id]->map(fn($m) => [
                    'file_name' => $m->media?->file_name,
                    'usage_type' => $m->usage_type,
                    'position' => $m->position,
                ])->toArray();
            }

            if ($profile->include_variants && $product->relationLoaded('variants')) {
                $productData['variants'] = $product->variants->map(fn($v) => [
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'ean' => $v->ean,
                    'status' => $v->status,
                ])->toArray();
            }

            $data['products'][] = $productData;
        }

        return $data;
    }

    private function resolveFileName(ExportProfile $profile, ?string $fileName): string
    {
        if ($fileName) {
            return $fileName;
        }

        $template = $profile->file_name_template;
        if (!$template) {
            $template = 'export-{date}';
        }

        $name = str_replace(
            ['{date}', '{profile}', '{format}'],
            [now()->format('Y-m-d'), $profile->name, $profile->format],
            $template,
        );

        return $name;
    }
}
