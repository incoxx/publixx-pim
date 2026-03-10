<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\Log;

/**
 * Importiert PIM-Daten aus BMEcat-XML-Dateien (1.2 und 2005).
 *
 * Nutzt den bestehenden ImportExecutor für die eigentliche Schreiblogik.
 * Parst BMEcat-XML via XMLReader (Streaming) + SimpleXMLElement (pro Element)
 * und wandelt die Daten in das vom ImportExecutor erwartete ParseResult-Format um.
 *
 * Unterstützte Transaktionstypen: T_NEW_CATALOG
 *
 * Import-Reihenfolge (via ParseResult → ImportExecutor):
 *   1. Produkttypen
 *   2. Attribute (aus FEATURE/FNAME)
 *   3. Preistypen (aus PRODUCT_PRICE/@price_type)
 *   4. Beziehungstypen (aus PRODUCT_REFERENCE/@type)
 *   5. Hierarchien (aus CATALOG_GROUP_SYSTEM)
 *   6. Hierarchie-Attribut-Zuordnungen
 *   7. Produkte
 *   8. Produktwerte (FEATURE-Werte)
 *   9. Produkt-Hierarchie-Zuordnungen
 *  10. Produktbeziehungen
 *  11. Preise
 *  12. Medien
 */
class BmecatFormatImporter
{
    private string $mode = 'update';

    /** BMEcat 2005 Namespace. */
    private const NS_2005 = 'http://www.bmecat.org/bmecat/2005';

    public function __construct(
        private readonly ImportExecutor $executor,
    ) {}

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert']) ? $mode : 'update';
    }

    /**
     * Importiert Daten aus einer BMEcat-XML-Datei.
     */
    public function importFromFile(string $filePath, ?string $productType = null): JsonImportResult
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Datei nicht gefunden: {$filePath}");
        }

        $xml = file_get_contents($filePath);

        return $this->importFromString($xml, $productType);
    }

    /**
     * Importiert Daten aus einem BMEcat-XML-String.
     */
    public function importFromString(string $xml, ?string $productType = null): JsonImportResult
    {
        $startTime = microtime(true);

        $version = $this->detectVersion($xml);
        $elementMap = BmecatElementMap::forVersion($version);

        Log::channel('import')->info('BMEcat-Import gestartet', [
            'version' => $version,
            'mode' => $this->mode,
            'product_type' => $productType ?? 'bmecat_product',
        ]);

        $this->executor->setMode($this->mode);

        $parsed = $this->parseXml($xml, $elementMap, $version);
        $parseResult = $this->buildParseResult($parsed, $productType);

        $result = $this->executor->execute($parseResult);

        $duration = round(microtime(true) - $startTime, 2);
        Log::channel('import')->info("BMEcat-Import abgeschlossen in {$duration}s", [
            'stats' => $result->stats,
            'affected_products' => count($result->affectedProductIds),
        ]);

        return new JsonImportResult(
            stats: $result->stats,
            affectedProductIds: $result->affectedProductIds,
            skippedDetails: $result->skippedDetails,
            durationSeconds: $duration,
        );
    }

    /**
     * Validiert die BMEcat-XML-Struktur ohne zu importieren.
     *
     * @return array{valid: bool, version: string|null, transaction_type: string|null, product_count: int, errors: string[]}
     */
    public function validate(string $xml): array
    {
        $errors = [];
        $version = null;
        $transactionType = null;
        $productCount = 0;

        // XML-Grundvalidierung
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $error) {
                $errors[] = "XML-Fehler Zeile {$error->line}: " . trim($error->message);
            }
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            return [
                'valid' => false,
                'version' => null,
                'transaction_type' => null,
                'product_count' => 0,
                'errors' => $errors,
            ];
        }
        libxml_use_internal_errors(false);

        // Version erkennen
        try {
            $version = $this->detectVersion($xml);
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        if ($version !== null) {
            $elementMap = BmecatElementMap::forVersion($version);

            // Root-Element prüfen
            $rootName = $doc->getName();
            if (strtoupper($rootName) !== 'BMECAT') {
                $errors[] = "Root-Element muss BMECAT sein, gefunden: {$rootName}";
            }

            // Transaktionstyp ermitteln
            $ns = BmecatElementMap::isVersion12($version) ? '' : self::NS_2005;
            $children = $ns ? $doc->children($ns) : $doc->children();

            foreach ($children as $child) {
                $name = strtoupper($child->getName());
                if (in_array($name, ['T_NEW_CATALOG', 'T_UPDATE_PRODUCTS', 'T_UPDATE_PRICES'])) {
                    $transactionType = $name;
                    break;
                }
            }

            if ($transactionType === null) {
                $errors[] = 'Kein Transaktionstyp gefunden (T_NEW_CATALOG, T_UPDATE_PRODUCTS, T_UPDATE_PRICES)';
            } elseif ($transactionType !== 'T_NEW_CATALOG') {
                $errors[] = "Transaktionstyp {$transactionType} wird aktuell nicht unterstützt. Nur T_NEW_CATALOG ist verfügbar.";
            }

            // Produkte zählen (Lightweight: nur Elemente zählen, nicht voll parsen)
            $productElement = $elementMap['product'];
            $productCount = substr_count(strtoupper($xml), "<{$productElement} ") + substr_count(strtoupper($xml), "<{$productElement}>");

            // HEADER prüfen
            $headerChildren = $ns ? $doc->children($ns)->HEADER : $doc->HEADER;
            if ($headerChildren === null || count($headerChildren) === 0) {
                $errors[] = 'HEADER-Element fehlt oder ist leer';
            }
        }

        return [
            'valid' => empty($errors),
            'version' => $version,
            'transaction_type' => $transactionType,
            'product_count' => $productCount,
            'errors' => $errors,
        ];
    }

    /**
     * Erkennt die BMEcat-Version aus dem XML.
     */
    public function detectVersion(string $xml): string
    {
        // Versuche version-Attribut zu lesen
        if (preg_match('/<BMECAT[^>]*\bversion\s*=\s*"([^"]+)"/i', $xml, $matches)) {
            $version = $matches[1];

            // Normalisierung
            if (str_starts_with($version, '2005')) {
                return $version;
            }
            if (str_starts_with($version, '1.2')) {
                return '1.2';
            }

            return $version;
        }

        // Fallback: Namespace prüfen
        if (str_contains($xml, 'http://www.bmecat.org/bmecat/2005')) {
            return '2005';
        }

        // Fallback: Element-Heuristik (ARTICLE = 1.2, PRODUCT = 2005)
        if (preg_match('/<ARTICLE[\s>]/i', $xml) && !preg_match('/<PRODUCT[\s>]/i', $xml)) {
            return '1.2';
        }

        // Standard: 2005
        return '2005';
    }

    /**
     * Parst das BMEcat-XML in eine intermediäre Datenstruktur.
     */
    private function parseXml(string $xml, array $elementMap, string $version): array
    {
        $isV12 = BmecatElementMap::isVersion12($version);
        $ns = $isV12 ? null : self::NS_2005;

        $parsed = [
            'header' => [],
            'default_currency' => 'EUR',
            'catalog_id' => '',
            'catalog_structures' => [],
            'products' => [],
            'product_to_group_maps' => [],
        ];

        $reader = new \XMLReader();
        $reader->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_NOCDATA);

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            $localName = strtoupper($reader->localName);

            switch ($localName) {
                case 'HEADER':
                    $parsed['header'] = $this->parseHeader($reader, $ns);
                    $parsed['default_currency'] = $parsed['header']['currency'] ?? 'EUR';
                    $parsed['catalog_id'] = $parsed['header']['catalog_id'] ?? '';
                    break;

                case 'CATALOG_GROUP_SYSTEM':
                    $parsed['catalog_structures'] = $this->parseCatalogGroupSystem($reader, $ns);
                    break;

                case $elementMap['product']:
                    $product = $this->parseProduct($reader, $elementMap, $ns, $parsed['default_currency']);
                    if ($product !== null) {
                        $parsed['products'][] = $product;
                    }
                    break;

                case $elementMap['product_to_cataloggroup_map']:
                    $map = $this->parseCatalogGroupMap($reader, $elementMap, $ns);
                    if ($map !== null) {
                        $parsed['product_to_group_maps'][] = $map;
                    }
                    break;
            }
        }

        $reader->close();

        return $parsed;
    }

    /**
     * Parst das HEADER-Element.
     */
    private function parseHeader(\XMLReader $reader, ?string $ns): array
    {
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);
        if ($el === null) {
            return [];
        }

        $catalog = $this->child($el, 'CATALOG', $ns);

        $header = [
            'language' => $catalog ? $this->text($catalog, 'LANGUAGE', $ns) : null,
            'catalog_id' => $catalog ? $this->text($catalog, 'CATALOG_ID', $ns) : null,
            'catalog_version' => $catalog ? $this->text($catalog, 'CATALOG_VERSION', $ns) : null,
            'catalog_name' => $catalog ? $this->text($catalog, 'CATALOG_NAME', $ns) : null,
            'currency' => ($catalog ? $this->text($catalog, 'CURRENCY', $ns) : null) ?: 'EUR',
        ];

        $supplier = $this->child($el, 'SUPPLIER', $ns);
        if ($supplier !== null) {
            $header['supplier_name'] = $this->text($supplier, 'SUPPLIER_NAME', $ns);
        }

        // Reader vorspulen bis nach dem HEADER
        $this->skipToEndElement($reader, 'HEADER');

        return $header;
    }

    /**
     * Parst das CATALOG_GROUP_SYSTEM.
     *
     * @return array<array{group_id: string, group_name: string, parent_id: string, type: string, order: int, description: ?string}>
     */
    private function parseCatalogGroupSystem(\XMLReader $reader, ?string $ns): array
    {
        $structures = [];
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);

        if ($el === null) {
            $this->skipToEndElement($reader, 'CATALOG_GROUP_SYSTEM');
            return [];
        }

        $catalogStructures = $ns
            ? $el->children($ns)->CATALOG_STRUCTURE
            : $el->CATALOG_STRUCTURE;

        if ($catalogStructures !== null) {
            foreach ($catalogStructures as $structure) {
                $type = (string) ($structure['type'] ?? 'node');
                $structures[] = [
                    'group_id' => $this->text($structure, 'GROUP_ID', $ns) ?? '',
                    'group_name' => $this->text($structure, 'GROUP_NAME', $ns) ?? '',
                    'parent_id' => $this->text($structure, 'PARENT_ID', $ns) ?? '0',
                    'type' => $type,
                    'order' => (int) ($this->text($structure, 'GROUP_ORDER', $ns) ?? 0),
                    'description' => $this->text($structure, 'GROUP_DESCRIPTION', $ns),
                ];
            }
        }

        $this->skipToEndElement($reader, 'CATALOG_GROUP_SYSTEM');

        return $structures;
    }

    /**
     * Parst ein einzelnes PRODUCT/ARTICLE-Element.
     */
    private function parseProduct(\XMLReader $reader, array $elementMap, ?string $ns, string $defaultCurrency): ?array
    {
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);

        if ($el === null) {
            $this->skipToEndElement($reader, $elementMap['product']);
            return null;
        }

        $sku = $this->text($el, $elementMap['supplier_pid'], $ns);
        if (empty($sku)) {
            $this->skipToEndElement($reader, $elementMap['product']);
            return null;
        }

        $product = [
            'sku' => $sku,
            'details' => [],
            'features' => [],
            'prices' => [],
            'references' => [],
            'media' => [],
            'catalog_group_maps' => [],
        ];

        // Details
        $details = $this->child($el, $elementMap['product_details'], $ns);
        if ($details !== null) {
            $product['details'] = [
                'description_short' => $this->text($details, 'DESCRIPTION_SHORT', $ns) ?? '',
                'description_long' => $this->text($details, 'DESCRIPTION_LONG', $ns),
                'ean' => $this->text($details, 'EAN', $ns),
                'manufacturer_name' => $this->text($details, 'MANUFACTURER_NAME', $ns),
                'manufacturer_pid' => $this->text($details, 'MANUFACTURER_PID', $ns),
                'delivery_time' => $this->text($details, 'DELIVERY_TIME', $ns),
                'keywords' => $this->texts($details, 'KEYWORD', $ns),
            ];
        }

        // Features (kann mehrere PRODUCT_FEATURES-Blöcke haben)
        $featureBlocks = $ns
            ? $el->children($ns)->{$elementMap['product_features']}
            : $el->{$elementMap['product_features']};

        if ($featureBlocks !== null) {
            foreach ($featureBlocks as $featureBlock) {
                $refSystemName = $this->text($featureBlock, 'REFERENCE_FEATURE_SYSTEM_NAME', $ns);
                $refGroupId = $this->text($featureBlock, 'REFERENCE_FEATURE_GROUP_ID', $ns);

                $features = $ns
                    ? $featureBlock->children($ns)->FEATURE
                    : $featureBlock->FEATURE;

                if ($features !== null) {
                    foreach ($features as $feature) {
                        $fname = $this->text($feature, 'FNAME', $ns);
                        if (empty($fname)) {
                            continue;
                        }

                        // Mehrere FVALUE-Elemente sammeln
                        $values = $this->texts($feature, 'FVALUE', $ns);

                        $product['features'][] = [
                            'fname' => $fname,
                            'values' => $values,
                            'funit' => $this->text($feature, 'FUNIT', $ns),
                            'forder' => (int) ($this->text($feature, 'FORDER', $ns) ?? 0),
                            'fdescr' => $this->text($feature, 'FDESCR', $ns),
                            'fvalue_type' => $this->text($feature, 'FVALUE_TYPE', $ns),
                            'ref_system' => $refSystemName,
                            'ref_group_id' => $refGroupId,
                        ];
                    }
                }
            }
        }

        // Price Details
        $priceDetails = $ns
            ? $el->children($ns)->{$elementMap['product_price_details']}
            : $el->{$elementMap['product_price_details']};

        if ($priceDetails !== null) {
            foreach ($priceDetails as $priceDetail) {
                // Gültigkeitszeitraum
                $validFrom = null;
                $validTo = null;
                $datetimes = $ns
                    ? $priceDetail->children($ns)->DATETIME
                    : $priceDetail->DATETIME;

                if ($datetimes !== null) {
                    foreach ($datetimes as $dt) {
                        $dtType = (string) ($dt['type'] ?? '');
                        $dtValue = trim((string) $dt);
                        if ($dtType === 'valid_start_date') {
                            $validFrom = $dtValue;
                        } elseif ($dtType === 'valid_end_date') {
                            $validTo = $dtValue;
                        }
                    }
                }

                // Einzelne Preise
                $prices = $ns
                    ? $priceDetail->children($ns)->{$elementMap['product_price']}
                    : $priceDetail->{$elementMap['product_price']};

                if ($prices !== null) {
                    foreach ($prices as $price) {
                        $priceType = (string) ($price['price_type'] ?? 'net_list');
                        $territories = $this->texts($price, 'TERRITORY', $ns);

                        $product['prices'][] = [
                            'price_type' => $priceType,
                            'amount' => $this->text($price, 'PRICE_AMOUNT', $ns) ?? '0',
                            'currency' => $this->text($price, 'PRICE_CURRENCY', $ns) ?? $defaultCurrency,
                            'tax' => $this->text($price, 'TAX', $ns),
                            'lower_bound' => $this->text($price, 'LOWER_BOUND', $ns),
                            'territories' => $territories,
                            'valid_from' => $validFrom,
                            'valid_to' => $validTo,
                        ];
                    }
                }
            }
        }

        // References
        $references = $ns
            ? $el->children($ns)->{$elementMap['product_reference']}
            : $el->{$elementMap['product_reference']};

        if ($references !== null) {
            foreach ($references as $ref) {
                $refType = (string) ($ref['type'] ?? 'similar');
                $targetId = $this->text($ref, $elementMap['prod_id_to'], $ns);
                if (!empty($targetId)) {
                    $product['references'][] = [
                        'type' => $refType,
                        'target_id' => $targetId,
                    ];
                }
            }
        }

        // MIME_INFO
        $mimeInfo = $this->child($el, 'MIME_INFO', $ns);
        if ($mimeInfo !== null) {
            $mimes = $ns
                ? $mimeInfo->children($ns)->MIME
                : $mimeInfo->MIME;

            if ($mimes !== null) {
                foreach ($mimes as $mime) {
                    $mimeSource = $this->text($mime, 'MIME_SOURCE', $ns);
                    if (!empty($mimeSource)) {
                        $product['media'][] = [
                            'mime_type' => $this->text($mime, 'MIME_TYPE', $ns),
                            'source' => $mimeSource,
                            'description' => $this->text($mime, 'MIME_DESCR', $ns),
                            'alt' => $this->text($mime, 'MIME_ALT', $ns),
                            'purpose' => $this->text($mime, 'MIME_PURPOSE', $ns) ?? 'normal',
                            'order' => (int) ($this->text($mime, 'MIME_ORDER', $ns) ?? 0),
                        ];
                    }
                }
            }
        }

        // Inline PRODUCT_TO_CATALOGGROUP_MAP
        $inlineMaps = $ns
            ? $el->children($ns)->{$elementMap['product_to_cataloggroup_map']}
            : $el->{$elementMap['product_to_cataloggroup_map']};

        if ($inlineMaps !== null) {
            foreach ($inlineMaps as $map) {
                $groupId = $this->text($map, 'CATALOG_GROUP_ID', $ns);
                if (!empty($groupId)) {
                    $product['catalog_group_maps'][] = [
                        'prod_id' => $sku,
                        'group_id' => $groupId,
                        'order' => (int) ($this->text($map, $elementMap['product_order'], $ns) ?? 0),
                    ];
                }
            }
        }

        $this->skipToEndElement($reader, $elementMap['product']);

        return $product;
    }

    /**
     * Parst ein standalone PRODUCT_TO_CATALOGGROUP_MAP / ARTICLE_TO_CATALOGGROUP_MAP.
     */
    private function parseCatalogGroupMap(\XMLReader $reader, array $elementMap, ?string $ns): ?array
    {
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);

        if ($el === null) {
            $this->skipToEndElement($reader, $elementMap['product_to_cataloggroup_map']);
            return null;
        }

        $prodId = $this->text($el, $elementMap['prod_id'], $ns);
        $groupId = $this->text($el, 'CATALOG_GROUP_ID', $ns);

        $this->skipToEndElement($reader, $elementMap['product_to_cataloggroup_map']);

        if (empty($prodId) || empty($groupId)) {
            return null;
        }

        return [
            'prod_id' => $prodId,
            'group_id' => $groupId,
            'order' => (int) ($this->text($el, $elementMap['product_order'], $ns) ?? 0),
        ];
    }

    /**
     * Baut das ParseResult aus den geparsten Daten.
     */
    private function buildParseResult(array $parsed, ?string $productType): ParseResult
    {
        $sheets = [];

        $productTypeName = $productType ?? 'bmecat_product';
        $catalogId = $parsed['catalog_id'] ?: 'bmecat_catalog';
        $hierarchyTechName = 'bmecat_' . $this->sanitizeTechnicalName($catalogId);

        // 1. Produkttypen
        $sheets['01_Produkttypen'] = [[
            'technical_name' => $productTypeName,
            'name_de' => $productTypeName === 'bmecat_product' ? 'BMEcat Produkt' : $productTypeName,
            'name_en' => null,
            'description' => 'Automatisch erstellt durch BMEcat-Import',
            'has_variants' => false,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
        ]];

        // 2. Hierarchien aus CATALOG_GROUP_SYSTEM
        $catalogGroupTree = $this->buildCatalogGroupTree($parsed['catalog_structures'] ?? []);
        if (!empty($catalogGroupTree)) {
            $sheets['06_Hierarchien'] = $this->buildHierarchyRows($catalogGroupTree, $hierarchyTechName);
        }

        // 3. Attribute sammeln (aus allen Produkt-Features deduplizieren)
        $attributeMap = []; // technical_name → ['name_de' => ..., 'data_type' => ...]
        $productValues = [];
        $allPriceTypes = [];
        $allRelationTypes = [];

        foreach ($parsed['products'] as $product) {
            $sku = $product['sku'];

            // Features → Attribute + Produktwerte
            foreach ($product['features'] as $feature) {
                $techName = $this->sanitizeTechnicalName($feature['fname']);
                if (empty($techName)) {
                    continue;
                }

                // Attribut deduplizieren
                if (!isset($attributeMap[$techName])) {
                    $dataType = $this->inferDataType($feature['values'], $feature['fvalue_type'] ?? null);
                    $attributeMap[$techName] = [
                        'technical_name' => $techName,
                        'name_de' => $feature['fname'],
                        'name_en' => null,
                        'description' => $feature['fdescr'],
                        'data_type' => $dataType,
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => count($feature['values']) > 1,
                        'max_multiplied' => count($feature['values']) > 1 ? 10 : null,
                        'is_translatable' => false,
                        'is_mandatory' => false,
                        'is_unique' => false,
                        'is_searchable' => true,
                        'is_inheritable' => true,
                        'parent_attribute' => null,
                        'source_system' => 'bmecat',
                        'views' => null,
                    ];
                }

                // Produktwerte (pro FVALUE ein Eintrag, bei Mehrfachwerten mit Index)
                foreach ($feature['values'] as $idx => $value) {
                    $productValues[] = [
                        'sku' => $sku,
                        'attribute' => $techName,
                        'value' => $value,
                        'unit' => $feature['funit'],
                        'language' => null,
                        'index' => count($feature['values']) > 1 ? $idx : 0,
                    ];
                }
            }

            // Preistypen sammeln
            foreach ($product['prices'] as $price) {
                $priceType = $price['price_type'];
                if (!isset($allPriceTypes[$priceType])) {
                    $allPriceTypes[$priceType] = [
                        'technical_name' => $priceType,
                        'name_de' => $this->translatePriceType($priceType),
                        'name_en' => $priceType,
                    ];
                }
            }

            // Beziehungstypen sammeln
            foreach ($product['references'] as $ref) {
                $refType = $ref['type'];
                if (!isset($allRelationTypes[$refType])) {
                    $allRelationTypes[$refType] = [
                        'technical_name' => $refType,
                        'name_de' => $this->translateRelationType($refType),
                        'name_en' => $refType,
                        'is_bidirectional' => in_array($refType, ['similar']),
                    ];
                }
            }
        }

        // Attribute Sheet
        if (!empty($attributeMap)) {
            $sheets['05_Attribute'] = array_values($attributeMap);
        }

        // Preistypen Sheet
        if (!empty($allPriceTypes)) {
            $sheets['16_Preistypen'] = array_values($allPriceTypes);
        }

        // Beziehungstypen Sheet
        if (!empty($allRelationTypes)) {
            $sheets['17_Beziehungstypen'] = array_values($allRelationTypes);
        }

        // 4. Hierarchie-Attribut-Zuordnungen
        if (!empty($catalogGroupTree) && !empty($attributeMap)) {
            $hierarchyAttributes = $this->buildHierarchyAttributeAssignments(
                $parsed['products'],
                $catalogGroupTree,
                $hierarchyTechName,
                $parsed['product_to_group_maps'],
            );
            if (!empty($hierarchyAttributes)) {
                $sheets['07_Hierarchie_Attribute'] = $hierarchyAttributes;
            }
        }

        // 5. Produkte
        $sheets['08_Produkte'] = array_map(fn (array $product) => [
            'sku' => $product['sku'],
            'name' => $product['details']['description_short'] ?? $product['sku'],
            'name_en' => null,
            'product_type' => $productTypeName,
            'ean' => $product['details']['ean'] ?? null,
            'status' => 'draft',
        ], $parsed['products']);

        // 6. Produktwerte
        if (!empty($productValues)) {
            $sheets['09_Produktwerte'] = $productValues;
        }

        // 7. Produkt-Hierarchie-Zuordnungen
        $productHierarchies = $this->buildProductHierarchyAssignments(
            $parsed['products'],
            $parsed['product_to_group_maps'],
            $catalogGroupTree,
            $hierarchyTechName,
        );
        if (!empty($productHierarchies)) {
            $sheets['11_Produkt_Hierarchien'] = $productHierarchies;
        }

        // 8. Produktbeziehungen
        $relations = [];
        $sortOrder = 0;
        foreach ($parsed['products'] as $product) {
            foreach ($product['references'] as $ref) {
                $relations[] = [
                    'source_sku' => $product['sku'],
                    'target_sku' => $ref['target_id'],
                    'relation_type' => $ref['type'],
                    'sort_order' => $sortOrder++,
                ];
            }
        }
        if (!empty($relations)) {
            $sheets['12_Produktbeziehungen'] = $relations;
        }

        // 9. Preise
        $prices = [];
        foreach ($parsed['products'] as $product) {
            foreach ($product['prices'] as $price) {
                $country = !empty($price['territories']) ? $price['territories'][0] : null;
                $prices[] = [
                    'sku' => $product['sku'],
                    'price_type' => $price['price_type'],
                    'amount' => (float) $price['amount'],
                    'currency' => $price['currency'],
                    'valid_from' => $price['valid_from'],
                    'valid_to' => $price['valid_to'],
                    'country' => $country,
                    'scale_from' => $price['lower_bound'] !== null ? (int) $price['lower_bound'] : null,
                    'scale_to' => null,
                ];
            }
        }
        if (!empty($prices)) {
            $sheets['13_Preise'] = $prices;
        }

        // 10. Medien
        $media = [];
        foreach ($parsed['products'] as $product) {
            foreach ($product['media'] as $idx => $m) {
                $media[] = [
                    'sku' => $product['sku'],
                    'file_name' => basename($m['source']),
                    'media_type' => $this->mapMimeTypeToMediaType($m['mime_type']),
                    'usage_type' => $this->mapMimePurpose($m['purpose']),
                    'title_de' => $m['description'],
                    'title_en' => null,
                    'alt_text_de' => $m['alt'],
                    'sort_order' => $m['order'] ?: $idx,
                    'is_primary' => $idx === 0,
                ];
            }
        }
        if (!empty($media)) {
            $sheets['14_Medien'] = $media;
        }

        return new ParseResult(
            sheetsFound: array_keys($sheets),
            data: $sheets,
        );
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    /**
     * Sanitisiert einen FNAME zu einem gültigen technical_name.
     */
    public function sanitizeTechnicalName(string $name): string
    {
        // Umlaute und Sonderzeichen transliterieren
        $name = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
        if ($name === false) {
            $name = mb_strtolower($name);
        }

        // Nicht-alphanumerische Zeichen durch Unterstriche ersetzen
        $name = (string) preg_replace('/[^a-z0-9_]/', '_', $name);

        // Mehrfache Unterstriche zusammenfassen
        $name = (string) preg_replace('/_+/', '_', $name);

        // Führende/abschließende Unterstriche entfernen
        $name = trim($name, '_');

        // Max. 100 Zeichen
        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
            $name = rtrim($name, '_');
        }

        return $name;
    }

    /**
     * Leitet den Datentyp aus den FVALUE-Werten ab.
     */
    private function inferDataType(array $values, ?string $fvalueType): string
    {
        // Expliziter Typ aus dem XML
        if ($fvalueType !== null) {
            return match (strtolower($fvalueType)) {
                'numeric', 'integer', 'count' => 'Number',
                'float', 'decimal' => 'Float',
                'boolean' => 'Flag',
                'date', 'datetime' => 'Date',
                default => 'String',
            };
        }

        // Aus den Werten ableiten
        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }
            // Numerische Werte zuerst prüfen (vor Flag, da '0'/'1' auch numerisch sind)
            if (is_numeric($value)) {
                return str_contains($value, '.') || str_contains($value, ',') ? 'Float' : 'Number';
            }
            if (in_array(strtolower($value), ['true', 'false', 'ja', 'nein', 'yes', 'no'])) {
                return 'Flag';
            }
        }

        return 'String';
    }

    /**
     * Baut den Kataloggruppen-Baum aus den flachen CATALOG_STRUCTURE-Einträgen.
     *
     * @return array<string, array{group_id: string, group_name: string, parent_id: string, children: string[], path: string[]}>
     */
    private function buildCatalogGroupTree(array $structures): array
    {
        if (empty($structures)) {
            return [];
        }

        $tree = [];
        foreach ($structures as $s) {
            $tree[$s['group_id']] = [
                'group_id' => $s['group_id'],
                'group_name' => $s['group_name'],
                'parent_id' => $s['parent_id'],
                'type' => $s['type'],
                'order' => $s['order'],
                'children' => [],
            ];
        }

        // Parent-Child-Beziehungen aufbauen
        foreach ($tree as $id => $node) {
            if (isset($tree[$node['parent_id']])) {
                $tree[$node['parent_id']]['children'][] = $id;
            }
        }

        return $tree;
    }

    /**
     * Löst den vollständigen Pfad (als Array von Knotennamen) für eine GROUP_ID auf.
     *
     * @return string[] Pfad-Komponenten von Root abwärts (ohne Root selbst)
     */
    private function resolveNodePath(string $groupId, array $tree): array
    {
        if (!isset($tree[$groupId])) {
            return [];
        }

        $path = [];
        $current = $groupId;
        $visited = [];

        while (isset($tree[$current])) {
            if (isset($visited[$current])) {
                break; // Zyklus-Schutz
            }
            $visited[$current] = true;

            $node = $tree[$current];

            // Root-Knoten nicht in den Pfad aufnehmen
            if ($node['type'] === 'root') {
                break;
            }

            array_unshift($path, $node['group_name']);
            $current = $node['parent_id'];
        }

        return $path;
    }

    /**
     * Baut die Hierarchie-Zeilen für das 06_Hierarchien-Sheet.
     */
    private function buildHierarchyRows(array $tree, string $hierarchyTechName): array
    {
        $rows = [];
        $processed = [];

        foreach ($tree as $node) {
            $path = $this->resolveNodePath($node['group_id'], $tree);

            if (empty($path)) {
                continue;
            }

            // Pfad als Key zur Deduplizierung
            $pathKey = implode('/', $path);
            if (isset($processed[$pathKey])) {
                continue;
            }
            $processed[$pathKey] = true;

            $row = [
                'hierarchy' => $hierarchyTechName,
                'type' => 'master',
                'level_1' => $path[0] ?? null,
                'level_2' => $path[1] ?? null,
                'level_3' => $path[2] ?? null,
                'level_4' => $path[3] ?? null,
                'level_5' => $path[4] ?? null,
                'level_6' => $path[5] ?? null,
            ];

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Baut Hierarchie-Attribut-Zuordnungen aus den Feature-Referenzen.
     */
    private function buildHierarchyAttributeAssignments(
        array $products,
        array $tree,
        string $hierarchyTechName,
        array $standaloneMaps = [],
    ): array {
        // Baue ein SKU→GroupIDs-Index aus inline + standalone Maps
        $skuToGroupIds = [];
        foreach ($products as $product) {
            foreach ($product['catalog_group_maps'] as $map) {
                $skuToGroupIds[$product['sku']][$map['group_id']] = true;
            }
        }
        foreach ($standaloneMaps as $map) {
            $skuToGroupIds[$map['prod_id']][$map['group_id']] = true;
        }

        // Sammle welche Attribute in welchen Knoten vorkommen
        $nodeAttributes = []; // group_id → [attribute_tech_name => true]

        foreach ($products as $product) {
            $productGroupIds = array_keys($skuToGroupIds[$product['sku']] ?? []);

            foreach ($product['features'] as $feature) {
                $techName = $this->sanitizeTechnicalName($feature['fname']);
                if (empty($techName)) {
                    continue;
                }

                foreach ($productGroupIds as $groupId) {
                    if (!isset($nodeAttributes[$groupId])) {
                        $nodeAttributes[$groupId] = [];
                    }
                    $nodeAttributes[$groupId][$techName] = true;
                }
            }
        }

        $rows = [];
        $sort = 0;
        foreach ($nodeAttributes as $groupId => $attributes) {
            $path = $this->resolveNodePath($groupId, $tree);
            if (empty($path)) {
                continue;
            }

            $nodePath = implode('/', $path);
            foreach (array_keys($attributes) as $attrTechName) {
                $rows[] = [
                    'hierarchy' => $hierarchyTechName,
                    'node_path' => $nodePath,
                    'attribute' => $attrTechName,
                    'collection_name' => null,
                    'collection_sort' => 0,
                    'attribute_sort' => $sort++,
                    'dont_inherit' => false,
                ];
            }
        }

        return $rows;
    }

    /**
     * Baut Produkt-Hierarchie-Zuordnungen.
     */
    private function buildProductHierarchyAssignments(
        array $products,
        array $standaloneMaps,
        array $tree,
        string $hierarchyTechName,
    ): array {
        if (empty($tree)) {
            return [];
        }

        $rows = [];
        $seen = []; // Deduplizierung: "sku|node_path"

        // Inline-Maps (aus Produkten)
        foreach ($products as $product) {
            foreach ($product['catalog_group_maps'] as $map) {
                $path = $this->resolveNodePath($map['group_id'], $tree);
                if (!empty($path)) {
                    $nodePath = implode('/', $path);
                    $key = $product['sku'] . '|' . $nodePath;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $rows[] = [
                            'sku' => $product['sku'],
                            'hierarchy' => $hierarchyTechName,
                            'node_path' => $nodePath,
                        ];
                    }
                }
            }
        }

        // Standalone-Maps
        foreach ($standaloneMaps as $map) {
            $path = $this->resolveNodePath($map['group_id'], $tree);
            if (!empty($path)) {
                $nodePath = implode('/', $path);
                $key = $map['prod_id'] . '|' . $nodePath;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $rows[] = [
                        'sku' => $map['prod_id'],
                        'hierarchy' => $hierarchyTechName,
                        'node_path' => $nodePath,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Übersetzt BMEcat price_type in deutschen Namen.
     */
    private function translatePriceType(string $type): string
    {
        return match ($type) {
            'net_list' => 'Nettolistenpreis',
            'gros_list' => 'Bruttolistenpreis',
            'net_customer' => 'Netto-Kundenpreis',
            'nrp' => 'Unverbindliche Preisempfehlung',
            'net_customer_exp' => 'Erwarteter Netto-Kundenpreis',
            default => $type,
        };
    }

    /**
     * Übersetzt BMEcat reference type in deutschen Namen.
     */
    private function translateRelationType(string $type): string
    {
        return match ($type) {
            'accessories' => 'Zubehör',
            'sparepart' => 'Ersatzteil',
            'similar' => 'Ähnliches Produkt',
            'followup' => 'Nachfolgeprodukt',
            'mandatory' => 'Pflicht-Zubehör',
            'select' => 'Auswahl-Zubehör',
            'diff_orderunit' => 'Andere Bestelleinheit',
            'consists_of' => 'Besteht aus',
            'others' => 'Sonstige',
            default => $type,
        };
    }

    /**
     * Mappt MIME_TYPE auf den PIM media_type.
     */
    private function mapMimeTypeToMediaType(?string $mimeType): string
    {
        if ($mimeType === null) {
            return 'image';
        }

        $mimeType = strtolower($mimeType);

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_contains($mimeType, 'pdf') || str_starts_with($mimeType, 'application/')) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Mappt MIME_PURPOSE auf den PIM usage_type.
     */
    private function mapMimePurpose(string $purpose): string
    {
        return match (strtolower($purpose)) {
            'normal' => 'gallery',
            'thumbnail' => 'teaser',
            'detail' => 'gallery',
            'data_sheet' => 'document',
            'logo' => 'teaser',
            default => 'gallery',
        };
    }

    // =========================================================================
    // XML-Hilfsmethoden
    // =========================================================================

    /**
     * Lädt ein XML-Fragment als SimpleXMLElement mit optionalem Namespace.
     */
    private function loadElement(string $outerXml, ?string $ns): ?\SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $el = simplexml_load_string($outerXml);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $el ?: null;
    }

    /**
     * Liest den Textinhalt eines Kind-Elements.
     */
    private function text(\SimpleXMLElement $parent, string $childName, ?string $ns): ?string
    {
        $child = $this->child($parent, $childName, $ns);
        if ($child === null) {
            return null;
        }

        $value = trim((string) $child);

        return $value !== '' ? $value : null;
    }

    /**
     * Liest alle Textinhalte gleichnamiger Kind-Elemente.
     *
     * @return string[]
     */
    private function texts(\SimpleXMLElement $parent, string $childName, ?string $ns): array
    {
        $values = [];

        if ($ns) {
            $children = $parent->children($ns)->{$childName};
        } else {
            $children = $parent->{$childName};
        }

        if ($children !== null) {
            foreach ($children as $child) {
                $value = trim((string) $child);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * Holt ein Kind-Element (mit oder ohne Namespace).
     */
    private function child(\SimpleXMLElement $parent, string $childName, ?string $ns): ?\SimpleXMLElement
    {
        if ($ns) {
            $children = $parent->children($ns)->{$childName};
        } else {
            $children = $parent->{$childName};
        }

        if ($children !== null && count($children) > 0) {
            return $children[0];
        }

        return null;
    }

    /**
     * Überspringt den XMLReader bis zum End-Element mit dem gegebenen Namen.
     */
    private function skipToEndElement(\XMLReader $reader, string $elementName): void
    {
        $depth = 1;
        while ($reader->read()) {
            $localName = strtoupper($reader->localName);
            if ($reader->nodeType === \XMLReader::ELEMENT && $localName === strtoupper($elementName) && !$reader->isEmptyElement) {
                $depth++;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $localName === strtoupper($elementName)) {
                $depth--;
                if ($depth <= 0) {
                    return;
                }
            }
        }
    }
}
