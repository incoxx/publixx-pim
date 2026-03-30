<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolver für GraphQL-Mutations: Produkte anlegen, ändern, löschen.
 *
 * Nutzt direkt Eloquent-Models für CRUD-Operationen.
 * Wird von GraphqlMutationBuilder als Resolve-Funktion eingebunden.
 */
class GraphqlMutationResolver
{
    /** Gecachte Attribute nach technical_name (für Batch-Performance) */
    private ?Collection $attributeCache = null;

    /**
     * Produkt anlegen mit optionalen Attributen, Preisen und Medien.
     */
    public function resolveCreateProduct(array $input): array
    {
        try {
            return DB::transaction(function () use ($input) {
                $product = Product::create([
                    'sku' => $input['sku'],
                    'name' => $input['name'],
                    'product_type_id' => $input['product_type_id'],
                    'ean' => $input['ean'] ?? null,
                    'status' => $input['status'] ?? 'draft',
                    'master_hierarchy_node_id' => $input['master_hierarchy_node_id'] ?? null,
                    'manufacturer_id' => $input['manufacturer_id'] ?? null,
                    'product_type_ref' => 'product',
                ]);

                $this->syncAttributes($product, $input['attributes'] ?? []);
                $this->syncPrices($product, $input['prices'] ?? []);
                $this->syncMedia($product, $input['media'] ?? []);

                Log::channel('export')->info("GraphQL Mutation: Produkt erstellt", [
                    'sku' => $product->sku,
                    'id' => $product->id,
                ]);

                return [
                    'success' => true,
                    'message' => "Produkt '{$product->sku}' erstellt.",
                    'product' => $this->productToArray($product),
                    'errors' => [],
                ];
            });
        } catch (\Throwable $e) {
            Log::channel('export')->error("GraphQL Mutation: createProduct fehlgeschlagen", [
                'sku' => $input['sku'] ?? 'unbekannt',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Produkt konnte nicht erstellt werden.',
                'product' => null,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Produkt aktualisieren (Lookup per ID oder SKU).
     */
    public function resolveUpdateProduct(?string $id, ?string $sku, array $input): array
    {
        try {
            $product = $this->findProduct($id, $sku);
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Produkt nicht gefunden.',
                    'product' => null,
                    'errors' => ['Kein Produkt mit der angegebenen ID oder SKU gefunden.'],
                ];
            }

            return DB::transaction(function () use ($product, $input) {
                $updateData = array_filter([
                    'name' => $input['name'] ?? null,
                    'ean' => $input['ean'] ?? null,
                    'status' => $input['status'] ?? null,
                    'product_type_id' => $input['product_type_id'] ?? null,
                    'master_hierarchy_node_id' => $input['master_hierarchy_node_id'] ?? null,
                    'manufacturer_id' => $input['manufacturer_id'] ?? null,
                ], fn ($v) => $v !== null);

                if (!empty($updateData)) {
                    $product->update($updateData);
                }

                if (isset($input['attributes'])) {
                    $this->syncAttributes($product, $input['attributes']);
                }
                if (isset($input['prices'])) {
                    $this->syncPrices($product, $input['prices']);
                }
                if (isset($input['media'])) {
                    $this->syncMedia($product, $input['media']);
                }

                $product->refresh();

                Log::channel('export')->info("GraphQL Mutation: Produkt aktualisiert", [
                    'sku' => $product->sku,
                    'id' => $product->id,
                ]);

                return [
                    'success' => true,
                    'message' => "Produkt '{$product->sku}' aktualisiert.",
                    'product' => $this->productToArray($product),
                    'errors' => [],
                ];
            });
        } catch (\Throwable $e) {
            Log::channel('export')->error("GraphQL Mutation: updateProduct fehlgeschlagen", [
                'id' => $id,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Produkt konnte nicht aktualisiert werden.',
                'product' => null,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Produkt löschen (Lookup per ID oder SKU).
     */
    public function resolveDeleteProduct(?string $id, ?string $sku): array
    {
        try {
            $product = $this->findProduct($id, $sku);
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Produkt nicht gefunden.',
                    'product' => null,
                    'errors' => ['Kein Produkt mit der angegebenen ID oder SKU gefunden.'],
                ];
            }

            // Abhängigkeiten prüfen (Varianten, Relationen)
            $constraints = $product->checkDeletionConstraints();
            if ($constraints['has_dependencies']) {
                $details = collect($constraints['dependencies'])
                    ->map(fn ($dep) => "{$dep['label']}: {$dep['count']}")
                    ->implode(', ');

                return [
                    'success' => false,
                    'message' => "Produkt hat Abhängigkeiten und kann nicht gelöscht werden.",
                    'product' => $this->productToArray($product),
                    'errors' => ["Abhängigkeiten: {$details}"],
                ];
            }

            $productData = $this->productToArray($product);
            $product->delete();

            Log::channel('export')->info("GraphQL Mutation: Produkt gelöscht", [
                'sku' => $productData['sku'],
                'id' => $productData['id'],
            ]);

            return [
                'success' => true,
                'message' => "Produkt '{$productData['sku']}' gelöscht.",
                'product' => $productData,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::channel('export')->error("GraphQL Mutation: deleteProduct fehlgeschlagen", [
                'id' => $id,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Produkt konnte nicht gelöscht werden.',
                'product' => null,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Batch-Import: Mehrere Produkte anlegen/aktualisieren.
     * Jedes Produkt wird einzeln verarbeitet (Savepoint), Fehler brechen nicht den Batch ab.
     */
    public function resolveImportProducts(array $products): array
    {
        // Attribute vorab cachen für Performance
        $this->attributeCache = Attribute::all()->keyBy('technical_name');

        $results = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($products as $productInput) {
            $sku = $productInput['sku'] ?? null;
            $existing = $sku ? Product::where('sku', $sku)->first() : null;

            if ($existing) {
                // Update
                $result = $this->resolveUpdateProduct($existing->id, null, $productInput);
                if ($result['success']) {
                    $updated++;
                } else {
                    $failed++;
                }
            } else {
                // Create
                $result = $this->resolveCreateProduct($productInput);
                if ($result['success']) {
                    $created++;
                } else {
                    $failed++;
                }
            }

            $results[] = $result;
        }

        // Cache leeren
        $this->attributeCache = null;

        return [
            'total' => count($products),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    private function findProduct(?string $id, ?string $sku): ?Product
    {
        if ($id) {
            return Product::find($id);
        }
        if ($sku) {
            return Product::where('sku', $sku)->first();
        }

        return null;
    }

    /**
     * Attributwerte setzen/aktualisieren.
     */
    private function syncAttributes(Product $product, array $attributes): void
    {
        foreach ($attributes as $attrInput) {
            $technicalName = $attrInput['technical_name'] ?? null;
            if (!$technicalName) {
                continue;
            }

            $attribute = $this->resolveAttribute($technicalName);
            if (!$attribute) {
                Log::channel('export')->warning("GraphQL Mutation: Attribut nicht gefunden", [
                    'technical_name' => $technicalName,
                    'product_sku' => $product->sku,
                ]);
                continue;
            }

            $valueColumn = $this->getValueColumn($attribute->data_type);
            $rawValue = $attrInput['value'] ?? null;
            $language = $attrInput['language'] ?? null;

            $conditions = [
                'product_id' => $product->id,
                'attribute_id' => $attribute->id,
                'language' => $language,
            ];

            $values = [
                'value_string' => null,
                'value_number' => null,
                'value_date' => null,
                'value_flag' => null,
            ];
            $values[$valueColumn] = $this->castValue($rawValue, $valueColumn);

            ProductAttributeValue::updateOrCreate($conditions, $values);
        }
    }

    /**
     * Preise setzen (Upsert per product_id + price_type_id + currency).
     */
    private function syncPrices(Product $product, array $prices): void
    {
        foreach ($prices as $priceInput) {
            $priceTypeId = $priceInput['price_type_id'] ?? null;
            if (!$priceTypeId) {
                continue;
            }

            ProductPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'price_type_id' => $priceTypeId,
                    'currency' => $priceInput['currency'] ?? 'EUR',
                ],
                [
                    'amount' => $priceInput['amount'],
                    'valid_from' => $priceInput['valid_from'] ?? null,
                    'valid_to' => $priceInput['valid_to'] ?? null,
                    'price_region_id' => $priceInput['price_region_id'] ?? null,
                    'scale_from' => $priceInput['scale_from'] ?? null,
                    'scale_to' => $priceInput['scale_to'] ?? null,
                ],
            );
        }
    }

    /**
     * Medien-Zuordnungen setzen (Upsert per product_id + media_id).
     */
    private function syncMedia(Product $product, array $media): void
    {
        foreach ($media as $mediaInput) {
            $mediaId = $mediaInput['media_id'] ?? null;
            if (!$mediaId) {
                continue;
            }

            ProductMediaAssignment::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'media_id' => $mediaId,
                ],
                [
                    'usage_type_id' => $mediaInput['usage_type_id'] ?? null,
                    'sort_order' => $mediaInput['sort_order'] ?? 0,
                    'is_primary' => $mediaInput['is_primary'] ?? false,
                ],
            );
        }
    }

    private function resolveAttribute(string $technicalName): ?Attribute
    {
        if ($this->attributeCache !== null) {
            return $this->attributeCache->get($technicalName);
        }

        return Attribute::where('technical_name', $technicalName)->first();
    }

    private function getValueColumn(string $dataType): string
    {
        return match (strtolower($dataType)) {
            'number', 'decimal', 'integer', 'float' => 'value_number',
            'boolean', 'flag' => 'value_flag',
            'date', 'datetime' => 'value_date',
            default => 'value_string',
        };
    }

    private function castValue(?string $rawValue, string $column): mixed
    {
        if ($rawValue === null) {
            return null;
        }

        return match ($column) {
            'value_number' => (float) $rawValue,
            'value_flag' => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN),
            'value_date' => $rawValue,
            default => $rawValue,
        };
    }

    private function productToArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'ean' => $product->ean,
            'status' => $product->status,
            'product_type_id' => $product->product_type_id,
            'master_hierarchy_node_id' => $product->master_hierarchy_node_id,
            'manufacturer_id' => $product->manufacturer_id,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
