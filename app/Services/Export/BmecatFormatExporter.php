<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Import\BmecatElementMap;
use Illuminate\Support\Collection;

/**
 * Exportiert PIM-Daten als BMEcat-XML (1.2 oder 2005).
 *
 * Unterstützt T_NEW_CATALOG und erlaubt Filterung nach Hierarchie,
 * Produkttypen, Attributen, Preistypen und Beziehungstypen.
 */
class BmecatFormatExporter
{
    private string $version = '2005';
    private array $elementMap = [];

    // Filter
    private ?string $hierarchyId = null;
    private array $productTypeIds = [];
    private array $attributeIds = [];
    private array $priceTypeIds = [];
    private array $relationTypeIds = [];

    /** @var string[] Attribute technical_names die als PRODUCT_DETAILS-Felder gemappt werden */
    private const DETAIL_ATTRIBUTES = [
        'description_long', 'beschreibung', 'beschreibung_lang',
        'manufacturer_name', 'hersteller', 'manufacturer_pid',
        'hersteller_artikelnummer', 'delivery_time', 'lieferzeit',
    ];

    public function setVersion(string $version): void
    {
        $this->version = in_array($version, ['1.2', '2005']) ? $version : '2005';
    }

    public function setHierarchyId(?string $id): void
    {
        $this->hierarchyId = $id;
    }

    public function setProductTypeIds(array $ids): void
    {
        $this->productTypeIds = $ids;
    }

    public function setAttributeIds(array $ids): void
    {
        $this->attributeIds = $ids;
    }

    public function setPriceTypeIds(array $ids): void
    {
        $this->priceTypeIds = $ids;
    }

    public function setRelationTypeIds(array $ids): void
    {
        $this->relationTypeIds = $ids;
    }

    /**
     * Exportiert PIM-Daten als BMEcat-XML-String.
     */
    public function export(): string
    {
        $this->elementMap = BmecatElementMap::forVersion($this->version);
        $isV12 = BmecatElementMap::isVersion12($this->version);

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');

        // Root element
        $xml->startElement('BMECAT');
        $xml->writeAttribute('version', $isV12 ? '1.2' : '2005');
        if (!$isV12) {
            $xml->writeAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');
        }

        // HEADER
        $this->writeHeader($xml);

        // T_NEW_CATALOG
        $xml->startElement('T_NEW_CATALOG');

        // Hierarchie (CATALOG_GROUP_SYSTEM)
        $hierarchy = $this->loadHierarchy();
        if ($hierarchy) {
            $this->writeCatalogGroupSystem($xml, $hierarchy);
        }

        // Produkte in Chunks verarbeiten um Memory-Verbrauch zu begrenzen
        $categoryMappings = [];
        $this->buildProductQuery()->chunk(500, function (Collection $products) use ($xml, $hierarchy, &$categoryMappings) {
            foreach ($products as $product) {
                $this->writeProduct($xml, $product);
            }

            // Kategorie-Zuordnungen für diesen Chunk sammeln
            if ($hierarchy) {
                $chunkMappings = $this->loadProductCategoryMap($hierarchy, $products);
                array_push($categoryMappings, ...$chunkMappings);
            }

            // Memory nach jedem Chunk freigeben
            $xml->flush();
        });

        // PRODUCT_TO_CATALOGGROUP_MAP (standalone)
        foreach ($categoryMappings as $mapping) {
            $this->writeProductToCatalogGroupMap($xml, $mapping);
        }

        $xml->endElement(); // T_NEW_CATALOG
        $xml->endElement(); // BMECAT
        $xml->endDocument();

        return $xml->outputMemory();
    }

    // =========================================================================
    // HEADER
    // =========================================================================

    private function writeHeader(\XMLWriter $xml): void
    {
        $xml->startElement('HEADER');
        $xml->startElement('CATALOG');
        $xml->writeElement('LANGUAGE', 'deu');
        $xml->writeElement('CATALOG_ID', 'PIM-EXPORT-' . date('Ymd'));
        $xml->writeElement('CATALOG_VERSION', '001.000');
        $xml->writeElement('CATALOG_NAME', config('app.name', 'PIM') . ' Export');
        $xml->writeElement('CURRENCY', 'EUR');
        $xml->endElement(); // CATALOG

        $xml->startElement('SUPPLIER');
        $xml->writeElement('SUPPLIER_NAME', config('app.name', 'PIM'));
        $xml->endElement(); // SUPPLIER

        $xml->endElement(); // HEADER
    }

    // =========================================================================
    // CATALOG_GROUP_SYSTEM (Hierarchy)
    // =========================================================================

    private function loadHierarchy(): ?Hierarchy
    {
        if (!$this->hierarchyId) {
            return null;
        }

        return Hierarchy::with(['nodes' => function ($q) {
            $q->where('is_active', true)->orderBy('depth')->orderBy('sort_order');
        }])->find($this->hierarchyId);
    }

    private function writeCatalogGroupSystem(\XMLWriter $xml, Hierarchy $hierarchy): void
    {
        $xml->startElement('CATALOG_GROUP_SYSTEM');
        $xml->writeElement('GROUP_SYSTEM_ID', $hierarchy->technical_name);
        $xml->writeElement('GROUP_SYSTEM_NAME', $hierarchy->name_de ?? $hierarchy->technical_name);

        // Parent-IDs vorberechnen um N+1 für leaf-Detection zu vermeiden
        $parentIds = $hierarchy->nodes->pluck('parent_node_id')->filter()->unique();

        foreach ($hierarchy->nodes as $node) {
            $this->writeCatalogStructure($xml, $node, $parentIds);
        }

        $xml->endElement(); // CATALOG_GROUP_SYSTEM
    }

    private function writeCatalogStructure(\XMLWriter $xml, HierarchyNode $node, Collection $parentIds): void
    {
        $xml->startElement('CATALOG_STRUCTURE');

        // type aus vorberechneten parentIds ableiten (keine DB-Query)
        $type = match (true) {
            $node->parent_node_id === null, $node->depth === 0 => 'root',
            !$parentIds->contains($node->id) => 'leaf',
            default => 'node',
        };
        $xml->writeAttribute('type', $type);

        $xml->writeElement('GROUP_ID', $node->id);
        $xml->writeElement('GROUP_NAME', $node->name_de ?? $node->id);

        if ($node->parent_node_id) {
            $xml->writeElement('PARENT_ID', $node->parent_node_id);
        } else {
            $xml->writeElement('PARENT_ID', '0');
        }

        if ($node->sort_order !== null) {
            $xml->writeElement('GROUP_ORDER', (string) $node->sort_order);
        }

        $xml->endElement(); // CATALOG_STRUCTURE
    }

    // =========================================================================
    // Products
    // =========================================================================

    /**
     * Baut die Produkt-Query mit allen nötigen Eager-Loads.
     */
    private function buildProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Product::query()
            ->where('status', '!=', 'inactive')
            ->whereNull('parent_product_id') // keine Varianten
            ->with([
                'attributeValues' => function ($q) {
                    if (!empty($this->attributeIds)) {
                        $q->whereIn('attribute_id', $this->attributeIds);
                    }
                    $q->where('is_inherited', false);
                },
                'attributeValues.attribute',
                'attributeValues.unit',
                'attributeValues.valueListEntry',
                'prices' => function ($q) {
                    if (!empty($this->priceTypeIds)) {
                        $q->whereIn('price_type_id', $this->priceTypeIds);
                    }
                },
                'prices.priceType',
                'outgoingRelations' => function ($q) {
                    if (!empty($this->relationTypeIds)) {
                        $q->whereIn('relation_type_id', $this->relationTypeIds);
                    }
                },
                'outgoingRelations.relationType',
                'outgoingRelations.targetProduct',
                'media',
            ]);

        if (!empty($this->productTypeIds)) {
            $query->whereIn('product_type_id', $this->productTypeIds);
        }

        return $query;
    }

    private function writeProduct(\XMLWriter $xml, Product $product): void
    {
        $el = $this->elementMap;

        $xml->startElement($el['product']); // PRODUCT or ARTICLE
        $xml->writeAttribute('mode', 'new');

        // SUPPLIER_PID / SUPPLIER_AID
        $xml->writeElement($el['supplier_pid'], $product->sku);

        // PRODUCT_DETAILS / ARTICLE_DETAILS
        $this->writeProductDetails($xml, $product);

        // PRODUCT_FEATURES / ARTICLE_FEATURES
        $this->writeProductFeatures($xml, $product);

        // PRODUCT_ORDER_DETAILS / ARTICLE_ORDER_DETAILS
        $this->writeProductOrderDetails($xml, $product);

        // PRODUCT_PRICE_DETAILS / ARTICLE_PRICE_DETAILS
        $this->writeProductPriceDetails($xml, $product);

        // MIME_INFO
        $this->writeMimeInfo($xml, $product);

        // PRODUCT_REFERENCE / ARTICLE_REFERENCE
        $this->writeProductReferences($xml, $product);

        $xml->endElement(); // PRODUCT/ARTICLE
    }

    private function writeProductDetails(\XMLWriter $xml, Product $product): void
    {
        $el = $this->elementMap;

        $xml->startElement($el['product_details']);

        $xml->writeElement('DESCRIPTION_SHORT', $product->name ?? '');

        // Langbeschreibung aus Attributen suchen
        $descLong = $this->getAttributeValue($product, 'description_long')
            ?? $this->getAttributeValue($product, 'beschreibung')
            ?? $this->getAttributeValue($product, 'beschreibung_lang');
        if ($descLong) {
            $xml->writeElement('DESCRIPTION_LONG', $descLong);
        }

        if ($product->ean) {
            $xml->writeElement('EAN', $product->ean);
        }

        // Hersteller aus Attributen
        $manufacturer = $this->getAttributeValue($product, 'manufacturer_name')
            ?? $this->getAttributeValue($product, 'hersteller');
        if ($manufacturer) {
            $xml->writeElement('MANUFACTURER_NAME', $manufacturer);
        }

        $manufacturerPid = $this->getAttributeValue($product, 'manufacturer_pid')
            ?? $this->getAttributeValue($product, 'hersteller_artikelnummer');
        if ($manufacturerPid) {
            $xml->writeElement('MANUFACTURER_PID', $manufacturerPid);
        }

        $deliveryTime = $this->getAttributeValue($product, 'delivery_time')
            ?? $this->getAttributeValue($product, 'lieferzeit');
        if ($deliveryTime) {
            $xml->writeElement('DELIVERY_TIME', $deliveryTime);
        }

        $xml->endElement(); // PRODUCT_DETAILS
    }

    private function writeProductFeatures(\XMLWriter $xml, Product $product): void
    {
        $values = $product->attributeValues->filter(function ($av) {
            return $av->attribute && !in_array($av->attribute->technical_name, self::DETAIL_ATTRIBUTES);
        });

        if ($values->isEmpty()) {
            return;
        }

        $el = $this->elementMap;
        $xml->startElement($el['product_features']);

        $order = 1;
        foreach ($values as $av) {
            $xml->startElement('FEATURE');
            $xml->writeElement('FNAME', $av->attribute->name_de ?? $av->attribute->technical_name);

            $value = $this->resolveAttributeValue($av);
            if ($value !== null) {
                $xml->writeElement('FVALUE', (string) $value);
            }

            if ($av->unit) {
                $xml->writeElement('FUNIT', $av->unit->symbol ?? $av->unit->technical_name);
            }

            $xml->writeElement('FORDER', (string) $order);
            $order++;

            $xml->endElement(); // FEATURE
        }

        $xml->endElement(); // PRODUCT_FEATURES
    }

    private function writeProductOrderDetails(\XMLWriter $xml, Product $product): void
    {
        $el = $this->elementMap;
        $xml->startElement($el['product_order_details']);

        // Bestelleinheit aus Attributen lesen, Default: C62 (Stück)
        $orderUnit = $this->getAttributeValue($product, 'order_unit')
            ?? $this->getAttributeValue($product, 'bestelleinheit')
            ?? 'C62';
        $contentUnit = $this->getAttributeValue($product, 'content_unit')
            ?? $this->getAttributeValue($product, 'inhaltseinheit')
            ?? $orderUnit;

        $xml->writeElement('ORDER_UNIT', $orderUnit);
        $xml->writeElement('CONTENT_UNIT', $contentUnit);
        $xml->writeElement('NO_CU_PER_OU', $this->getAttributeValue($product, 'no_cu_per_ou') ?? '1');
        $xml->writeElement('QUANTITY_MIN', $this->getAttributeValue($product, 'quantity_min') ?? '1');
        $xml->writeElement('QUANTITY_INTERVAL', $this->getAttributeValue($product, 'quantity_interval') ?? '1');
        $xml->endElement();
    }

    private function writeProductPriceDetails(\XMLWriter $xml, Product $product): void
    {
        if ($product->prices->isEmpty()) {
            return;
        }

        $el = $this->elementMap;
        $xml->startElement($el['product_price_details']);

        foreach ($product->prices as $price) {
            $xml->startElement($el['product_price']);
            $xml->writeAttribute('price_type', $price->priceType->technical_name ?? 'net_list');

            $xml->writeElement('PRICE_AMOUNT', number_format((float) $price->amount, 2, '.', ''));

            if ($price->currency) {
                $xml->writeElement('PRICE_CURRENCY', $price->currency);
            }

            // Steuersatz: aus Config oder Default 0.19
            $tax = config('pim.export.default_tax_rate', 0.19);
            $xml->writeElement('TAX', number_format((float) $tax, 2, '.', ''));

            if ($price->scale_from !== null) {
                $xml->writeElement('LOWER_BOUND', (string) $price->scale_from);
            }

            if ($price->country) {
                $xml->writeElement('TERRITORY', $price->country);
            }

            $xml->endElement(); // PRODUCT_PRICE
        }

        $xml->endElement(); // PRODUCT_PRICE_DETAILS
    }

    private function writeMimeInfo(\XMLWriter $xml, Product $product): void
    {
        // media ist per eager-load geladen
        if (!$product->relationLoaded('media') || $product->media->isEmpty()) {
            return;
        }

        $xml->startElement('MIME_INFO');

        $order = 1;
        foreach ($product->media as $medium) {
            $xml->startElement('MIME');
            $xml->writeElement('MIME_TYPE', $medium->mime_type ?? 'image/jpeg');
            $xml->writeElement('MIME_SOURCE', $medium->file_name ?? $medium->path ?? '');

            $purpose = $medium->pivot->usage_type ?? 'normal';
            $xml->writeElement('MIME_PURPOSE', $this->mapMimePurpose($purpose));
            $xml->writeElement('MIME_ORDER', (string) $order);
            $order++;

            $xml->endElement(); // MIME
        }

        $xml->endElement(); // MIME_INFO
    }

    private function writeProductReferences(\XMLWriter $xml, Product $product): void
    {
        if ($product->outgoingRelations->isEmpty()) {
            return;
        }

        $el = $this->elementMap;

        foreach ($product->outgoingRelations as $relation) {
            if (!$relation->targetProduct) {
                continue;
            }

            $xml->startElement($el['product_reference']);
            $xml->writeAttribute('type', $relation->relationType->technical_name ?? 'similar');
            $xml->writeElement($el['prod_id_to'], $relation->targetProduct->sku);
            $xml->endElement();
        }
    }

    // =========================================================================
    // Product-to-CatalogGroup Map
    // =========================================================================

    private function loadProductCategoryMap(Hierarchy $hierarchy, Collection $products): array
    {
        $productIds = $products->pluck('id')->toArray();
        if (empty($productIds)) {
            return [];
        }

        $nodeIds = $hierarchy->nodes->pluck('id')->toArray();

        $assignments = OutputHierarchyProductAssignment::whereIn('product_id', $productIds)
            ->whereIn('hierarchy_node_id', $nodeIds)
            ->with(['product', 'hierarchyNode'])
            ->get();

        $result = [];
        foreach ($assignments as $assignment) {
            if ($assignment->product && $assignment->hierarchyNode) {
                $result[] = [
                    'sku' => $assignment->product->sku,
                    'group_id' => $assignment->hierarchyNode->id,
                    'sort_order' => $assignment->sort_order ?? 1,
                ];
            }
        }

        return $result;
    }

    private function writeProductToCatalogGroupMap(\XMLWriter $xml, array $mapping): void
    {
        $el = $this->elementMap;

        $xml->startElement($el['product_to_cataloggroup_map']);
        $xml->writeElement($el['prod_id'], $mapping['sku']);
        $xml->writeElement('CATALOG_GROUP_ID', $mapping['group_id']);
        $xml->writeElement($el['product_order'], (string) $mapping['sort_order']);
        $xml->endElement();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getAttributeValue(Product $product, string $technicalName): ?string
    {
        $av = $product->attributeValues->first(function ($av) use ($technicalName) {
            return $av->attribute && $av->attribute->technical_name === $technicalName;
        });

        if (!$av) {
            return null;
        }

        return $this->resolveAttributeValue($av);
    }

    private function resolveAttributeValue(ProductAttributeValue $av): ?string
    {
        if ($av->value_string !== null && $av->value_string !== '') {
            return $av->value_string;
        }
        if ($av->value_number !== null) {
            return rtrim(rtrim(number_format((float) $av->value_number, 6, '.', ''), '0'), '.');
        }
        if ($av->value_date !== null) {
            return $av->value_date instanceof \DateTimeInterface
                ? $av->value_date->format('Y-m-d')
                : (string) $av->value_date;
        }
        if ($av->value_flag !== null) {
            return $av->value_flag ? 'true' : 'false';
        }
        if ($av->value_selection_id !== null && $av->valueListEntry) {
            return $av->valueListEntry->value_de ?? $av->valueListEntry->technical_name ?? (string) $av->value_selection_id;
        }

        return null;
    }

    private function mapMimePurpose(string $usageType): string
    {
        return match ($usageType) {
            'gallery', 'main', 'normal' => 'normal',
            'teaser', 'thumbnail', 'thumb' => 'thumbnail',
            'detail', 'zoom' => 'detail',
            'logo' => 'logo',
            'data_sheet', 'datasheet', 'pdf' => 'data_sheet',
            default => 'normal',
        };
    }
}
