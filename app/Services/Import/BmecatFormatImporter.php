<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Exceptions\ImportCancelledException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importiert PIM-Daten aus BMEcat-XML-Dateien (1.2 und 2005).
 *
 * Nutzt den bestehenden ImportExecutor für die eigentliche Schreiblogik.
 * Parst BMEcat-XML via XMLReader (Streaming) + SimpleXMLElement (pro Element)
 * und wandelt die Daten in das vom ImportExecutor erwartete ParseResult-Format um.
 *
 * Unterstützte Transaktionstypen: T_NEW_CATALOG, T_UPDATE_PRODUCTS
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

    /** Eindeutige Import-ID für Cancel-Tracking. */
    private ?string $importId = null;

    /** Optional parsing progress callback: fn(int $productsParsed) */
    private $parsingProgressCallback = null;

    /** Optional build-phase progress callback: fn(string $step, int $current, int $total) */
    private $buildProgressCallback = null;

    /** Optional heartbeat callback to keep SSE connection alive. */
    private $heartbeatCallback = null;

    /** BMEcat 2005 Namespace. */
    private const NS_2005 = 'http://www.bmecat.org/bmecat/2005';

    /** ISO 639-2/B → ISO 639-1 Sprachcode-Mapping. */
    private const LANG_MAP = [
        'deu' => 'de', 'ger' => 'de',
        'eng' => 'en',
        'fra' => 'fr', 'fre' => 'fr',
        'ita' => 'it',
        'spa' => 'es',
        'por' => 'pt',
        'nld' => 'nl', 'dut' => 'nl',
        'pol' => 'pl',
        'ces' => 'cs', 'cze' => 'cs',
        'hun' => 'hu',
        'ron' => 'ro', 'rum' => 'ro',
        'slk' => 'sk', 'slo' => 'sk',
        'slv' => 'sl',
        'hrv' => 'hr',
        'srp' => 'sr',
        'bul' => 'bg',
        'dan' => 'da',
        'fin' => 'fi',
        'swe' => 'sv',
        'nor' => 'no', 'nob' => 'nb', 'nno' => 'nn',
        'ell' => 'el', 'gre' => 'el',
        'tur' => 'tr',
        'rus' => 'ru',
        'ukr' => 'uk',
        'zho' => 'zh', 'chi' => 'zh',
        'jpn' => 'ja',
        'kor' => 'ko',
        'ara' => 'ar',
    ];

    public function __construct(
        private readonly ImportExecutor $executor,
    ) {}

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert']) ? $mode : 'update';
    }

    /**
     * Setzt die Import-ID für Cancel-Tracking.
     */
    public function setImportId(string $importId): void
    {
        $this->importId = $importId;
        $this->executor->setImportId($importId);
    }

    /**
     * Gibt die Import-ID zurück (erzeugt eine neue falls nicht gesetzt).
     */
    public function getImportId(): string
    {
        if ($this->importId === null) {
            $this->importId = Str::uuid()->toString();
            $this->executor->setImportId($this->importId);
        }

        return $this->importId;
    }

    /**
     * Setzt eine Callback-Funktion für Fortschrittsmeldungen.
     */
    public function setProgressCallback(callable $callback): void
    {
        $this->executor->setProgressCallback($callback);
    }

    /**
     * Setzt eine Callback-Funktion für Parsing-Fortschrittsmeldungen.
     *
     * @param callable(int $productsParsed): void $callback
     */
    public function setParsingProgressCallback(callable $callback): void
    {
        $this->parsingProgressCallback = $callback;
    }

    /**
     * Setzt eine Heartbeat-Funktion die regelmäßig aufgerufen wird.
     */
    public function setHeartbeatCallback(callable $callback): void
    {
        $this->heartbeatCallback = $callback;
        $this->executor->setHeartbeatCallback($callback);
    }

    /**
     * Setzt eine Callback-Funktion für Fortschritt während der Datenaufbereitung.
     *
     * @param callable(string $step, int $current, int $total): void $callback
     */
    public function setBuildProgressCallback(callable $callback): void
    {
        $this->buildProgressCallback = $callback;
    }

    /**
     * Sendet einen Heartbeat um die SSE-Verbindung offen zu halten.
     */
    private function heartbeat(): void
    {
        if ($this->heartbeatCallback) {
            ($this->heartbeatCallback)();
        }
    }

    /**
     * Sendet Build-Phase-Fortschritt und Heartbeat.
     */
    private function sendBuildProgress(string $step, int $current, int $total): void
    {
        if ($this->buildProgressCallback) {
            ($this->buildProgressCallback)($step, $current, $total);
        }
        $this->heartbeat();
    }

    /**
     * Prüft ob der Import abgebrochen wurde.
     *
     * @throws ImportCancelledException
     */
    private function checkCancelled(): void
    {
        if ($this->importId === null) {
            return;
        }

        if (Cache::get("bmecat_import_cancel_{$this->importId}")) {
            Cache::forget("bmecat_import_cancel_{$this->importId}");
            Log::channel('import')->info("Import {$this->importId} wurde vom Benutzer abgebrochen");
            throw new ImportCancelledException($this->importId);
        }
    }

    /**
     * Bricht einen laufenden Import ab.
     */
    public static function cancelImport(string $importId): void
    {
        Cache::put("bmecat_import_cancel_{$importId}", true, 300);
    }

    /**
     * Importiert Daten aus einer BMEcat-XML-Datei.
     * Nutzt file-basiertes XMLReader-Streaming für geringen Speicherverbrauch.
     */
    public function importFromFile(string $filePath, ?string $productType = null): JsonImportResult
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Datei nicht gefunden: {$filePath}");
        }

        $fileSize = filesize($filePath);
        $isLargeFile = $fileSize > 10 * 1024 * 1024; // > 10 MB

        if ($isLargeFile) {
            return $this->importFromFilePath($filePath, $productType);
        }

        $xml = file_get_contents($filePath);

        return $this->importFromString($xml, $productType);
    }

    /**
     * Importiert aus Dateipfad mit file-basiertem XMLReader (spart RAM bei großen Dateien).
     */
    public function importFromFilePath(string $filePath, ?string $productType = null): JsonImportResult
    {
        $startTime = microtime(true);
        $importId = $this->getImportId();

        $version = $this->detectVersionFromFile($filePath);
        $elementMap = BmecatElementMap::forVersion($version);

        $fileSize = filesize($filePath);
        $fileSizeMb = round($fileSize / 1024 / 1024, 1);

        Log::channel('import')->info('BMEcat-Import gestartet (Datei-Modus)', [
            'import_id' => $importId,
            'version' => $version,
            'mode' => $this->mode,
            'product_type' => $productType ?? 'bmecat_product',
            'file_size_mb' => $fileSizeMb,
        ]);

        $this->checkCancelled();

        try {
            $parsed = $this->parseXmlFromFile($filePath, $elementMap, $version);
        } catch (ImportCancelledException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-XML konnte nicht geparst werden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('BMEcat-XML konnte nicht geparst werden: ' . $e->getMessage(), 0, $e);
        }

        // T_UPDATE_PRODUCTS erzwingt update-Modus (bestehende Daten nicht löschen)
        $effectiveMode = $this->mode;
        if (($parsed['transaction_type'] ?? '') === 'T_UPDATE_PRODUCTS') {
            $effectiveMode = 'update';
            Log::channel('import')->info('T_UPDATE_PRODUCTS erkannt — Modus auf "update" gesetzt (bestehende Zuordnungen bleiben erhalten).');
        }
        $this->executor->setMode($effectiveMode);

        $this->checkCancelled();

        $productCount = count($parsed['products']);

        try {
            $parseResult = $this->buildParseResult($parsed, $productType);
        } catch (ImportCancelledException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('import')->error('Import-Daten konnten nicht aufbereitet werden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Import-Daten konnten nicht aufbereitet werden: ' . $e->getMessage(), 0, $e);
        }

        Log::channel('import')->info("Parsing abgeschlossen: {$productCount} Produkte geparst, starte DB-Import");

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

        $this->checkCancelled();

        try {
            $parsed = $this->parseXml($xml, $elementMap, $version);
        } catch (ImportCancelledException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-XML konnte nicht geparst werden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('BMEcat-XML konnte nicht geparst werden: ' . $e->getMessage(), 0, $e);
        }

        // T_UPDATE_PRODUCTS erzwingt update-Modus (bestehende Daten nicht löschen)
        $effectiveMode = $this->mode;
        if (($parsed['transaction_type'] ?? '') === 'T_UPDATE_PRODUCTS') {
            $effectiveMode = 'update';
            Log::channel('import')->info('T_UPDATE_PRODUCTS erkannt — Modus auf "update" gesetzt (bestehende Zuordnungen bleiben erhalten).');
        }
        $this->executor->setMode($effectiveMode);

        $this->checkCancelled();

        try {
            $parseResult = $this->buildParseResult($parsed, $productType);
        } catch (ImportCancelledException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('import')->error('Import-Daten konnten nicht aufbereitet werden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Import-Daten konnten nicht aufbereitet werden: ' . $e->getMessage(), 0, $e);
        }

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
     * Führt folgende Prüfungen durch:
     * 1. XML-Wohlgeformtheit (Syntax)
     * 2. Versionserkennung
     * 3. XSD-Schema-Validierung (Schema-Fehler → warnings, nicht errors)
     * 4. Strukturprüfung (HEADER, Transaktionstyp, Produkte)
     * 5. Erkennung gemischter 1.2/2005-Elemente
     *
     * @return array{valid: bool, version: string|null, transaction_type: string|null, product_count: int, errors: string[], warnings: string[]}
     */
    public function validate(string $xml): array
    {
        $errors = [];
        $warnings = [];
        $version = null;
        $transactionType = null;
        $productCount = 0;

        // 1. XML-Grundvalidierung (Wohlgeformtheit)
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
                'warnings' => [],
            ];
        }
        libxml_use_internal_errors(false);

        // 2. Version erkennen
        try {
            $version = $this->detectVersion($xml);
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        if ($version === null) {
            return [
                'valid' => false,
                'version' => null,
                'transaction_type' => null,
                'product_count' => 0,
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        $elementMap = BmecatElementMap::forVersion($version);
        $isV12 = BmecatElementMap::isVersion12($version);

        // Root-Element prüfen
        $rootName = $doc->getName();
        if (strtoupper($rootName) !== 'BMECAT') {
            $errors[] = "Root-Element muss BMECAT sein, gefunden: {$rootName}";
        }

        // 3. XSD-Schema-Validierung (Fehler → warnings, blockieren den Import nicht)
        $schemaWarnings = $this->validateAgainstSchema($xml, $version);
        if (!empty($schemaWarnings)) {
            $warnings = array_merge($warnings, $schemaWarnings);
        }

        // 4. Strukturprüfung
        $hasNs = str_contains($xml, self::NS_2005);
        $ns = ($isV12 || !$hasNs) ? '' : self::NS_2005;
        $children = $ns ? $doc->children($ns) : $doc->children();

        // Transaktionstyp
        foreach ($children as $child) {
            $name = strtoupper($child->getName());
            if (in_array($name, ['T_NEW_CATALOG', 'T_UPDATE_PRODUCTS', 'T_UPDATE_PRICES'])) {
                $transactionType = $name;
                break;
            }
        }

        if ($transactionType === null) {
            $errors[] = 'Kein Transaktionstyp gefunden (T_NEW_CATALOG, T_UPDATE_PRODUCTS, T_UPDATE_PRICES)';
        } elseif (!in_array($transactionType, ['T_NEW_CATALOG', 'T_UPDATE_PRODUCTS'])) {
            $errors[] = "Transaktionstyp {$transactionType} wird aktuell nicht unterstützt. Verfügbar: T_NEW_CATALOG, T_UPDATE_PRODUCTS.";
        }

        // Produkte zählen (beide Varianten: PRODUCT und ARTICLE)
        $xmlUpper = strtoupper($xml);
        $productElement = $elementMap['product'];
        $productCount = substr_count($xmlUpper, "<{$productElement} ") + substr_count($xmlUpper, "<{$productElement}>");

        // Auch alternative Elementnamen zählen (gemischte Dokumente)
        $altProductElement = $isV12 ? 'PRODUCT' : 'ARTICLE';
        $altCount = substr_count($xmlUpper, "<{$altProductElement} ") + substr_count($xmlUpper, "<{$altProductElement}>");
        if ($altCount > 0 && $productCount === 0) {
            $productCount = $altCount;
        }

        // HEADER prüfen
        $headerChildren = $ns ? $doc->children($ns)->HEADER : $doc->HEADER;
        if ($headerChildren === null || count($headerChildren) === 0) {
            $errors[] = 'HEADER-Element fehlt oder ist leer';
        }

        if ($productCount === 0) {
            $warnings[] = 'Keine Produkte gefunden. Die Datei enthält möglicherweise keine Produktdaten.';
        }

        // 5. Gemischte Elementnamen erkennen (1.2-Elemente in 2005-Dokument oder umgekehrt)
        $mixedWarnings = $this->detectMixedElementNames($xml, $version);
        $warnings = array_merge($warnings, $mixedWarnings);

        return [
            'valid' => empty($errors),
            'version' => $version,
            'transaction_type' => $transactionType,
            'product_count' => $productCount,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validiert XML gegen das passende XSD-Schema.
     *
     * @return string[] Schema-Validierungswarnungen (leeres Array = alles OK)
     */
    private function validateAgainstSchema(string $xml, string $version): array
    {
        $isV12 = BmecatElementMap::isVersion12($version);
        $schemaFile = $isV12 ? 'bmecat_12.xsd' : 'bmecat_2005.xsd';
        $schemaPath = __DIR__ . '/schemas/' . $schemaFile;

        if (!file_exists($schemaPath)) {
            return ["Schema-Datei nicht gefunden: {$schemaFile}"];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $warnings = [];
        if (!$dom->schemaValidate($schemaPath)) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $error) {
                $msg = trim($error->message);
                // Elementnamen aus der Schema-Fehlermeldung extrahieren für lesbarere Meldungen
                $warnings[] = "Schema-Warnung Zeile {$error->line}: {$msg}";
            }
            libxml_clear_errors();
        }
        libxml_use_internal_errors(false);

        return $warnings;
    }

    /**
     * Erkennt gemischte 1.2/2005-Elementnamen in einem BMEcat-Dokument.
     *
     * @return string[] Warnungen über falsche Elementnamen
     */
    private function detectMixedElementNames(string $xml, string $version): array
    {
        $warnings = [];
        $isV12 = BmecatElementMap::isVersion12($version);

        // BMEcat 2005 Dokument mit 1.2-Elementen?
        if (!$isV12) {
            $v12Elements = [
                'ARTICLE_TO_CATALOGGROUP_MAP' => 'PRODUCT_TO_CATALOGGROUP_MAP',
                'ARTICLE_DETAILS' => 'PRODUCT_DETAILS',
                'ARTICLE_FEATURES' => 'PRODUCT_FEATURES',
                'ARTICLE_PRICE_DETAILS' => 'PRODUCT_PRICE_DETAILS',
                'ARTICLE_PRICE' => 'PRODUCT_PRICE',
                'ARTICLE_REFERENCE' => 'PRODUCT_REFERENCE',
                'ARTICLE_ORDER_DETAILS' => 'PRODUCT_ORDER_DETAILS',
                'SUPPLIER_AID' => 'SUPPLIER_PID',
                'ART_ID' => 'PROD_ID',
                'ART_ID_TO' => 'PROD_ID_TO',
                'MANUFACTURER_AID' => 'MANUFACTURER_PID',
            ];

            foreach ($v12Elements as $oldName => $newName) {
                if (preg_match("/<{$oldName}[\s>]/", $xml)) {
                    $warnings[] = "BMEcat 1.2-Element <{$oldName}> gefunden — in BMEcat 2005 heißt es <{$newName}>. Der Import versucht es trotzdem zu verarbeiten.";
                }
            }
        }

        // BMEcat 1.2 Dokument mit 2005-Elementen?
        if ($isV12) {
            $v2005Elements = [
                'PRODUCT_TO_CATALOGGROUP_MAP' => 'ARTICLE_TO_CATALOGGROUP_MAP',
                'PRODUCT_DETAILS' => 'ARTICLE_DETAILS',
                'PRODUCT_FEATURES' => 'ARTICLE_FEATURES',
                'PRODUCT_PRICE_DETAILS' => 'ARTICLE_PRICE_DETAILS',
                'PRODUCT_PRICE' => 'ARTICLE_PRICE',
                'SUPPLIER_PID' => 'SUPPLIER_AID',
                'PROD_ID' => 'ART_ID',
            ];

            foreach ($v2005Elements as $newName => $oldName) {
                if (preg_match("/<{$newName}[\s>]/", $xml)) {
                    $warnings[] = "BMEcat 2005-Element <{$newName}> gefunden — in BMEcat 1.2 heißt es <{$oldName}>. Der Import versucht es trotzdem zu verarbeiten.";
                }
            }
        }

        return $warnings;
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
     * Erkennt die BMEcat-Version aus den ersten Bytes einer Datei.
     * Liest nur die ersten 8KB statt die gesamte Datei.
     */
    private function detectVersionFromFile(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return '2005';
        }

        $header = fread($handle, 8192);
        fclose($handle);

        if ($header === false) {
            return '2005';
        }

        return $this->detectVersion($header);
    }

    /**
     * Parst das BMEcat-XML aus einer Datei via file-basiertem XMLReader.
     * Spart ~200MB RAM bei großen Dateien gegenüber String-basiertem Parsing.
     *
     * @throws ImportCancelledException
     */
    private function parseXmlFromFile(string $filePath, array $elementMap, string $version): array
    {
        $isV12 = BmecatElementMap::isVersion12($version);

        // Namespace-Erkennung aus den ersten Bytes
        $handle = fopen($filePath, 'r');
        $header = $handle !== false ? fread($handle, 8192) : '';
        if ($handle !== false) {
            fclose($handle);
        }
        $hasNs = str_contains($header ?: '', self::NS_2005);
        $ns = ($isV12 || !$hasNs) ? null : self::NS_2005;

        $altElementMap = BmecatElementMap::forVersion($isV12 ? '2005' : '1.2');

        $parsed = [
            'header' => [],
            'default_currency' => 'EUR',
            'default_language' => null,
            'catalog_id' => '',
            'catalog_structures' => [],
            'products' => [],
            'product_to_group_maps' => [],
            'transaction_type' => 'T_NEW_CATALOG',
        ];

        $reader = new \XMLReader();
        if (!$reader->open($filePath, 'UTF-8', LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new \RuntimeException("Datei konnte nicht zum Parsen geöffnet werden: {$filePath}");
        }

        $productName = strtoupper($elementMap['product']);
        $altProductName = strtoupper($altElementMap['product']);
        $mapName = strtoupper($elementMap['product_to_cataloggroup_map']);
        $altMapName = strtoupper($altElementMap['product_to_cataloggroup_map']);

        $productsParsed = 0;
        $lastCancelCheck = 0;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            $localName = strtoupper($reader->localName);

            // Transaktionstyp erkennen
            if (in_array($localName, ['T_NEW_CATALOG', 'T_UPDATE_PRODUCTS', 'T_UPDATE_PRICES'])) {
                $parsed['transaction_type'] = $localName;
                continue;
            }

            if ($localName === 'HEADER') {
                $parsed['header'] = $this->parseHeader($reader, $ns);
                $parsed['default_currency'] = $parsed['header']['currency'] ?? 'EUR';
                $parsed['catalog_id'] = $parsed['header']['catalog_id'] ?? '';
                if (!empty($parsed['header']['language'])) {
                    $parsed['default_language'] = $this->mapLanguageCode($parsed['header']['language']);
                }
            } elseif ($localName === 'CATALOG_GROUP_SYSTEM') {
                $parsed['catalog_structures'] = $this->parseCatalogGroupSystem($reader, $ns);
            } elseif ($localName === $productName || $localName === $altProductName) {
                $actualElementName = $reader->localName;
                $product = $this->parseProduct($reader, $elementMap, $altElementMap, $ns, $parsed['default_currency'], $parsed['default_language'], $actualElementName);
                if ($product !== null) {
                    $parsed['products'][] = $product;
                    $productsParsed++;

                    // Parsing-Progress alle 100 Produkte senden
                    if ($this->parsingProgressCallback && $productsParsed % 100 === 0) {
                        ($this->parsingProgressCallback)($productsParsed);
                    }

                    // Cancel-Check alle 500 Produkte
                    if ($productsParsed - $lastCancelCheck >= 500) {
                        $lastCancelCheck = $productsParsed;
                        $this->checkCancelled();
                    }
                }
            } elseif ($localName === $mapName || $localName === $altMapName) {
                $actualElementName = $reader->localName;
                $map = $this->parseCatalogGroupMap($reader, $elementMap, $altElementMap, $ns, $actualElementName);
                if ($map !== null) {
                    $parsed['product_to_group_maps'][] = $map;
                }
            }
        }

        $reader->close();

        // Finalen Parsing-Progress senden
        if ($this->parsingProgressCallback && $productsParsed > 0) {
            ($this->parsingProgressCallback)($productsParsed);
        }

        return $parsed;
    }

    /**
     * Parst das BMEcat-XML in eine intermediäre Datenstruktur.
     *
     * Tolerant: Akzeptiert auch gemischte 1.2/2005-Elementnamen.
     */
    private function parseXml(string $xml, array $elementMap, string $version): array
    {
        $isV12 = BmecatElementMap::isVersion12($version);
        // BMEcat 2005 files may omit the namespace (e.g. DOCTYPE-based files)
        $hasNs = str_contains($xml, self::NS_2005);
        $ns = ($isV12 || !$hasNs) ? null : self::NS_2005;

        // Fallback-ElementMap für gemischte Dokumente:
        // BMEcat 2005 akzeptiert auch 1.2-Namen und umgekehrt
        $altElementMap = BmecatElementMap::forVersion($isV12 ? '2005' : '1.2');

        $parsed = [
            'header' => [],
            'default_currency' => 'EUR',
            'default_language' => null,
            'catalog_id' => '',
            'catalog_structures' => [],
            'products' => [],
            'product_to_group_maps' => [],
            'transaction_type' => 'T_NEW_CATALOG',
        ];

        $reader = new \XMLReader();
        $reader->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_NOCDATA);

        // Erwartete und alternative Elementnamen für den Switch
        $productName = strtoupper($elementMap['product']);
        $altProductName = strtoupper($altElementMap['product']);
        $mapName = strtoupper($elementMap['product_to_cataloggroup_map']);
        $altMapName = strtoupper($altElementMap['product_to_cataloggroup_map']);

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            $localName = strtoupper($reader->localName);

            // Transaktionstyp erkennen
            if (in_array($localName, ['T_NEW_CATALOG', 'T_UPDATE_PRODUCTS', 'T_UPDATE_PRICES'])) {
                $parsed['transaction_type'] = $localName;
                continue;
            }

            if ($localName === 'HEADER') {
                $parsed['header'] = $this->parseHeader($reader, $ns);
                $parsed['default_currency'] = $parsed['header']['currency'] ?? 'EUR';
                $parsed['catalog_id'] = $parsed['header']['catalog_id'] ?? '';
                if (!empty($parsed['header']['language'])) {
                    $parsed['default_language'] = $this->mapLanguageCode($parsed['header']['language']);
                }
            } elseif ($localName === 'CATALOG_GROUP_SYSTEM') {
                $parsed['catalog_structures'] = $this->parseCatalogGroupSystem($reader, $ns);
            } elseif ($localName === $productName || $localName === $altProductName) {
                // Tolerant: Akzeptiert PRODUCT und ARTICLE in beiden Versionen
                $actualElementName = $reader->localName;
                $product = $this->parseProduct($reader, $elementMap, $altElementMap, $ns, $parsed['default_currency'], $parsed['default_language'], $actualElementName);
                if ($product !== null) {
                    $parsed['products'][] = $product;
                }
            } elseif ($localName === $mapName || $localName === $altMapName) {
                // Tolerant: Akzeptiert PRODUCT_TO_CATALOGGROUP_MAP und ARTICLE_TO_CATALOGGROUP_MAP
                $actualElementName = $reader->localName;
                $map = $this->parseCatalogGroupMap($reader, $elementMap, $altElementMap, $ns, $actualElementName);
                if ($map !== null) {
                    $parsed['product_to_group_maps'][] = $map;
                }
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
            Log::channel('import')->error('HEADER-Element konnte nicht geparst werden');
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
            Log::channel('import')->error('CATALOG_GROUP_SYSTEM konnte nicht geparst werden');
            $this->skipToEndElement($reader, 'CATALOG_GROUP_SYSTEM');
            return [];
        }

        $catalogStructures = $ns
            ? $el->children($ns)->CATALOG_STRUCTURE
            : $el->CATALOG_STRUCTURE;

        if ($catalogStructures !== null) {
            foreach ($catalogStructures as $structure) {
                $type = $this->attr($structure, 'type') ?? 'node';
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
     *
     * Tolerant: Versucht beide Element-Map-Varianten (primär + alternativ).
     */
    private function parseProduct(\XMLReader $reader, array $elementMap, array $altElementMap, ?string $ns, string $defaultCurrency, ?string $defaultLang = null, ?string $actualElementName = null): ?array
    {
        $skipName = $actualElementName ?? $elementMap['product'];
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);

        if ($el === null) {
            Log::channel('import')->error('Produkt übersprungen: XML-Fragment konnte nicht geparst werden');
            $this->skipToEndElement($reader, $skipName);
            return null;
        }

        // Tolerant: Versuche erst primäre, dann alternative SKU-Elementnamen
        $sku = $this->text($el, $elementMap['supplier_pid'], $ns)
            ?? $this->text($el, $altElementMap['supplier_pid'], $ns);
        if (empty($sku)) {
            Log::channel('import')->warning('Produkt übersprungen: Keine SKU (SUPPLIER_PID/SUPPLIER_AID) gefunden');
            $this->skipToEndElement($reader, $skipName);
            return null;
        }

        $product = [
            'sku' => $sku,
            'details' => [],
            'features' => [],
            'udx_fields' => [],
            'prices' => [],
            'references' => [],
            'media' => [],
            'catalog_group_maps' => [],
        ];

        // Details (mit Mehrsprachigkeit) — tolerant: beide Elementnamen
        $details = $this->child($el, $elementMap['product_details'], $ns)
            ?? $this->child($el, $altElementMap['product_details'], $ns);
        if ($details !== null) {
            // Mehrsprachige Texte: {lang → text} — kein defaultLang,
            // damit einsprachige Inhalte nicht fälschlich als übersetzbar erkannt werden
            $shortTexts = $this->textsMultilang($details, 'DESCRIPTION_SHORT', $ns);
            $longTexts = $this->textsMultilang($details, 'DESCRIPTION_LONG', $ns);
            $keywordTexts = $this->textsMultilang($details, 'KEYWORD', $ns);

            // Erste Sprache oder Default für den Produktnamen
            $descShort = reset($shortTexts) ?: '';

            $product['details'] = [
                'description_short' => $descShort,
                'description_short_i18n' => $shortTexts,
                'description_long' => reset($longTexts) ?: null,
                'description_long_i18n' => $longTexts,
                'ean' => $this->text($details, 'EAN', $ns),
                'manufacturer_name' => $this->text($details, 'MANUFACTURER_NAME', $ns),
                'manufacturer_pid' => $this->text($details, 'MANUFACTURER_PID', $ns)
                    ?? $this->text($details, 'MANUFACTURER_AID', $ns),
                'delivery_time' => $this->text($details, 'DELIVERY_TIME', $ns),
                'keywords' => $this->texts($details, 'KEYWORD', $ns),
                'keywords_i18n' => $keywordTexts,
            ];
        }

        // Features (kann mehrere PRODUCT_FEATURES-Blöcke haben) — tolerant
        $featureBlocks = $ns
            ? $el->children($ns)->{$elementMap['product_features']}
            : $el->{$elementMap['product_features']};
        // Fallback: alternative Elementnamen
        if (($featureBlocks === null || count($featureBlocks) === 0) && $elementMap['product_features'] !== $altElementMap['product_features']) {
            $featureBlocks = $ns
                ? $el->children($ns)->{$altElementMap['product_features']}
                : $el->{$altElementMap['product_features']};
        }

        if ($featureBlocks !== null) {
            foreach ($featureBlocks as $featureBlock) {
                $refSystemName = $this->text($featureBlock, 'REFERENCE_FEATURE_SYSTEM_NAME', $ns);
                $refGroupId = $this->text($featureBlock, 'REFERENCE_FEATURE_GROUP_ID', $ns);

                $features = $ns
                    ? $featureBlock->children($ns)->FEATURE
                    : $featureBlock->FEATURE;

                if ($features !== null) {
                    foreach ($features as $feature) {
                        // FNAME kann mehrsprachig sein: xml:lang="deu" / xml:lang="eng"
                        $fnameI18n = $this->textsMultilang($feature, 'FNAME', $ns);
                        $fname = $this->text($feature, 'FNAME', $ns);
                        if (empty($fname)) {
                            continue;
                        }

                        // Englischen FNAME extrahieren (falls vorhanden)
                        $fnameEn = $fnameI18n['en'] ?? null;
                        // Falls der erste FNAME deutsch ist, fname_de verwenden; sonst fname als fallback
                        $fnameDe = $fnameI18n['de'] ?? $fname;

                        // Mehrere FVALUE-Elemente sammeln (mit optionalem xml:lang)
                        $values = $this->texts($feature, 'FVALUE', $ns);
                        $valuesI18n = $this->textsMultilang($feature, 'FVALUE', $ns);

                        $product['features'][] = [
                            'fname' => $fnameDe,
                            'fname_en' => $fnameEn,
                            'values' => $values,
                            'values_i18n' => $valuesI18n,
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

        // Price Details — tolerant
        $priceDetails = $ns
            ? $el->children($ns)->{$elementMap['product_price_details']}
            : $el->{$elementMap['product_price_details']};
        if (($priceDetails === null || count($priceDetails) === 0) && $elementMap['product_price_details'] !== $altElementMap['product_price_details']) {
            $priceDetails = $ns
                ? $el->children($ns)->{$altElementMap['product_price_details']}
                : $el->{$altElementMap['product_price_details']};
        }

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
                        $dtType = $this->attr($dt, 'type') ?? '';
                        $dtValue = trim((string) $dt);
                        if ($dtType === 'valid_start_date') {
                            $validFrom = $dtValue;
                        } elseif ($dtType === 'valid_end_date') {
                            $validTo = $dtValue;
                        }
                    }
                }

                // Einzelne Preise — tolerant
                $prices = $ns
                    ? $priceDetail->children($ns)->{$elementMap['product_price']}
                    : $priceDetail->{$elementMap['product_price']};
                if (($prices === null || count($prices) === 0) && $elementMap['product_price'] !== $altElementMap['product_price']) {
                    $prices = $ns
                        ? $priceDetail->children($ns)->{$altElementMap['product_price']}
                        : $priceDetail->{$altElementMap['product_price']};
                }

                if ($prices !== null) {
                    foreach ($prices as $price) {
                        $priceType = $this->attr($price, 'price_type') ?? $this->attr($price, 'type') ?? 'net_list';
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

        // References — tolerant
        $references = $ns
            ? $el->children($ns)->{$elementMap['product_reference']}
            : $el->{$elementMap['product_reference']};
        if (($references === null || count($references) === 0) && $elementMap['product_reference'] !== $altElementMap['product_reference']) {
            $references = $ns
                ? $el->children($ns)->{$altElementMap['product_reference']}
                : $el->{$altElementMap['product_reference']};
        }

        if ($references !== null) {
            foreach ($references as $ref) {
                $refType = $this->attr($ref, 'type') ?? 'similar';
                $targetId = $this->text($ref, $elementMap['prod_id_to'], $ns)
                    ?? $this->text($ref, $altElementMap['prod_id_to'], $ns);
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

        // USER_DEFINED_EXTENSIONS (UDX) — nur auf Produkt-Ebene
        $udxBlock = $this->child($el, 'USER_DEFINED_EXTENSIONS', $ns);
        if ($udxBlock !== null) {
            $product['udx_fields'] = $this->parseUdxFields($udxBlock, $ns);
        }

        // Inline PRODUCT_TO_CATALOGGROUP_MAP — tolerant
        $inlineMaps = $ns
            ? $el->children($ns)->{$elementMap['product_to_cataloggroup_map']}
            : $el->{$elementMap['product_to_cataloggroup_map']};
        if (($inlineMaps === null || count($inlineMaps) === 0) && $elementMap['product_to_cataloggroup_map'] !== $altElementMap['product_to_cataloggroup_map']) {
            $inlineMaps = $ns
                ? $el->children($ns)->{$altElementMap['product_to_cataloggroup_map']}
                : $el->{$altElementMap['product_to_cataloggroup_map']};
        }

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

        $this->skipToEndElement($reader, $skipName);

        return $product;
    }

    /**
     * Parst ein standalone PRODUCT_TO_CATALOGGROUP_MAP / ARTICLE_TO_CATALOGGROUP_MAP.
     *
     * Tolerant: Versucht beide Element-Map-Varianten (primär + alternativ).
     */
    private function parseCatalogGroupMap(\XMLReader $reader, array $elementMap, array $altElementMap, ?string $ns, ?string $actualElementName = null): ?array
    {
        $skipName = $actualElementName ?? $elementMap['product_to_cataloggroup_map'];
        $outerXml = $reader->readOuterXml();
        $el = $this->loadElement($outerXml, $ns);

        if ($el === null) {
            Log::channel('import')->warning('PRODUCT_TO_CATALOGGROUP_MAP übersprungen: XML konnte nicht geparst werden');
            $this->skipToEndElement($reader, $skipName);
            return null;
        }

        // Tolerant: Versuche beide Varianten für PROD_ID/ART_ID
        $prodId = $this->text($el, $elementMap['prod_id'], $ns)
            ?? $this->text($el, $altElementMap['prod_id'], $ns);
        $groupId = $this->text($el, 'CATALOG_GROUP_ID', $ns);

        $this->skipToEndElement($reader, $skipName);

        if (empty($prodId) || empty($groupId)) {
            return null;
        }

        return [
            'prod_id' => $prodId,
            'group_id' => $groupId,
            'order' => (int) ($this->text($el, $elementMap['product_order'], $ns)
                ?? $this->text($el, $altElementMap['product_order'], $ns)
                ?? 0),
        ];
    }

    /**
     * Baut das ParseResult aus den geparsten Daten.
     */
    private function buildParseResult(array &$parsed, ?string $productType): ParseResult
    {
        // Caches für diesen Build-Durchlauf zurücksetzen
        $this->nodePathCache = [];
        $this->sanitizeCache = [];

        $sheets = [];
        $totalProducts = count($parsed['products']);

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

        $this->sendBuildProgress('Hierarchien aufbauen', 0, $totalProducts);

        // 2. Hierarchien aus CATALOG_GROUP_SYSTEM
        $catalogGroupTree = $this->buildCatalogGroupTree($parsed['catalog_structures'] ?? []);

        // Standalone Maps → skuToGroupIds (vor Haupt-Loop)
        $skuToGroupIds = [];
        foreach ($parsed['product_to_group_maps'] as $map) {
            $skuToGroupIds[$map['prod_id']][$map['group_id']] = true;
        }

        // Fehlende Gruppen aus Maps in den Tree einfügen (wenn kein CATALOG_GROUP_SYSTEM)
        $allGroupIds = [];
        foreach ($parsed['product_to_group_maps'] as $map) {
            $allGroupIds[$map['group_id']] = true;
        }
        foreach ($parsed['products'] as $product) {
            foreach ($product['catalog_group_maps'] ?? [] as $map) {
                $allGroupIds[$map['group_id']] = true;
            }
        }
        foreach ($allGroupIds as $groupId => $_) {
            if (!isset($catalogGroupTree[$groupId])) {
                // Gruppe fehlt im CATALOG_GROUP_SYSTEM → als flachen Knoten anlegen
                $catalogGroupTree[$groupId] = [
                    'group_id' => $groupId,
                    'group_name' => $groupId,
                    'parent_id' => '0',
                    'type' => 'node',
                    'order' => 0,
                    'children' => [],
                ];
                Log::channel('import')->info("Kataloggruppe '{$groupId}' aus PRODUCT_TO_CATALOGGROUP_MAP als Knoten angelegt (kein CATALOG_GROUP_SYSTEM).");
            }
        }

        if (!empty($catalogGroupTree)) {
            $sheets['06_Hierarchien'] = $this->buildHierarchyRows($catalogGroupTree, $hierarchyTechName);
        }
        $hasTree = !empty($catalogGroupTree);

        // =====================================================================
        // Single-Pass: Alle Produktdaten in einem Durchlauf sammeln.
        // Jedes Produkt wird nach Verarbeitung sofort freigegeben um Speicher
        // zu sparen (~1.5 GB bei 110K Produkten aus 229 MB XML).
        // =====================================================================
        $this->sendBuildProgress('Produkte verarbeiten', 0, $totalProducts);
        $this->checkCancelled();

        $attributeMap = [];
        $productValues = [];
        $allPriceTypes = [];
        $allRelationTypes = [];
        $udxGroupMap = [];
        $udxAttributeMap = [];
        $nodeAttributes = []; // group_id → [attribute_tech_name → true]
        $productRows = [];
        $relations = [];
        $prices = [];
        $media = [];
        $productHierarchies = [];
        $seenHierarchy = [];
        $sortOrderRelations = 0;
        $seenProductSkus = [];

        $productKeys = array_keys($parsed['products']);
        $loopIndex = 0;

        foreach ($productKeys as $productIdx) {
            $product = $parsed['products'][$productIdx];
            unset($parsed['products'][$productIdx]); // Sofort freigeben

            $loopIndex++;
            if ($loopIndex % 500 === 0) {
                $this->sendBuildProgress('Produkte verarbeiten', $loopIndex, $totalProducts);
                $this->checkCancelled();
            }

            $sku = $product['sku'];

            // Duplikat-SKUs überspringen (letztes Vorkommen gewinnt bei Features/Werten,
            // aber Produkt-Zeile wird nur einmal angelegt)
            if (isset($seenProductSkus[$sku])) {
                Log::channel('import')->debug("Duplikat-SKU übersprungen: '{$sku}'");
                continue;
            }
            $seenProductSkus[$sku] = true;

            // --- Features → Attribute + Produktwerte ---
            foreach ($product['features'] as $feature) {
                $techName = $this->sanitizeTechnicalName($feature['fname']);
                if (empty($techName)) {
                    continue;
                }

                $valuesI18n = $feature['values_i18n'] ?? [];
                $hasExplicitLang = $this->hasExplicitLanguage($valuesI18n);
                $hasMultipleLangs = $hasExplicitLang;

                if (!isset($attributeMap[$techName])) {
                    $dataType = $this->inferDataType($feature['values'], $feature['fvalue_type'] ?? null);
                    $attributeMap[$techName] = [
                        'technical_name' => $techName,
                        'name_de' => $feature['fname'],
                        'name_en' => $feature['fname_en'] ?? null,
                        'description' => $feature['fdescr'],
                        'data_type' => $dataType,
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => count($feature['values']) > 1,
                        'max_multiplied' => count($feature['values']) > 1 ? 10 : null,
                        'is_translatable' => $hasMultipleLangs,
                        'is_mandatory' => false,
                        'is_unique' => false,
                        'is_searchable' => true,
                        'is_inheritable' => true,
                        'parent_attribute' => null,
                        'source_system' => 'bmecat',
                        'views' => null,
                    ];
                } else {
                    if ($hasMultipleLangs && !$attributeMap[$techName]['is_translatable']) {
                        $attributeMap[$techName]['is_translatable'] = true;
                    }
                    if (!empty($feature['fname_en']) && empty($attributeMap[$techName]['name_en'])) {
                        $attributeMap[$techName]['name_en'] = $feature['fname_en'];
                    }
                }

                if ($hasMultipleLangs) {
                    foreach ($valuesI18n as $lang => $value) {
                        $productValues[] = [
                            'sku' => $sku,
                            'attribute' => $techName,
                            'value' => $value,
                            'unit' => $feature['funit'],
                            'language' => $lang,
                            'index' => 0,
                        ];
                    }
                } else {
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
            }

            // --- Beschreibungen ---
            $descShortI18n = $product['details']['description_short_i18n'] ?? [];
            $descLongI18n = $product['details']['description_long_i18n'] ?? [];

            if (!empty($descShortI18n)) {
                $techName = 'description_short';
                $hasMultiLang = $this->hasExplicitLanguage($descShortI18n);
                if (!isset($attributeMap[$techName])) {
                    $attributeMap[$techName] = [
                        'technical_name' => $techName,
                        'name_de' => 'Kurzbeschreibung',
                        'name_en' => 'Short Description',
                        'description' => null,
                        'data_type' => 'String',
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => false,
                        'max_multiplied' => null,
                        'is_translatable' => $hasMultiLang,
                        'is_mandatory' => false,
                        'is_unique' => false,
                        'is_searchable' => true,
                        'is_inheritable' => true,
                        'parent_attribute' => null,
                        'source_system' => 'bmecat',
                        'views' => null,
                    ];
                } elseif ($hasMultiLang && !$attributeMap[$techName]['is_translatable']) {
                    $attributeMap[$techName]['is_translatable'] = true;
                }
                if ($hasMultiLang) {
                    foreach ($descShortI18n as $lang => $value) {
                        $productValues[] = [
                            'sku' => $sku,
                            'attribute' => $techName,
                            'value' => $value,
                            'unit' => null,
                            'language' => $lang,
                            'index' => 0,
                        ];
                    }
                } else {
                    $productValues[] = [
                        'sku' => $sku,
                        'attribute' => $techName,
                        'value' => reset($descShortI18n),
                        'unit' => null,
                        'language' => null,
                        'index' => 0,
                    ];
                }
            }

            if (!empty($descLongI18n)) {
                $techName = 'description_long';
                $hasMultiLang = $this->hasExplicitLanguage($descLongI18n);
                if (!isset($attributeMap[$techName])) {
                    $attributeMap[$techName] = [
                        'technical_name' => $techName,
                        'name_de' => 'Langbeschreibung',
                        'name_en' => 'Long Description',
                        'description' => null,
                        'data_type' => 'String',
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => false,
                        'max_multiplied' => null,
                        'is_translatable' => $hasMultiLang,
                        'is_mandatory' => false,
                        'is_unique' => false,
                        'is_searchable' => true,
                        'is_inheritable' => true,
                        'parent_attribute' => null,
                        'source_system' => 'bmecat',
                        'views' => null,
                    ];
                } elseif ($hasMultiLang && !$attributeMap[$techName]['is_translatable']) {
                    $attributeMap[$techName]['is_translatable'] = true;
                }
                if ($hasMultiLang) {
                    foreach ($descLongI18n as $lang => $value) {
                        $productValues[] = [
                            'sku' => $sku,
                            'attribute' => $techName,
                            'value' => $value,
                            'unit' => null,
                            'language' => $lang,
                            'index' => 0,
                        ];
                    }
                } else {
                    $productValues[] = [
                        'sku' => $sku,
                        'attribute' => $techName,
                        'value' => reset($descLongI18n),
                        'unit' => null,
                        'language' => null,
                        'index' => 0,
                    ];
                }
            }

            // --- UDX-Felder ---
            // Maximalen Index pro Attribut tracken (für is_multipliable)
            $udxMaxIndex = [];
            foreach ($product['udx_fields'] as $udxField) {
                $ns = $udxField['namespace'];
                $key = $udxField['key'];

                $groupTechName = $this->udxNamespaceToGroupTechName($ns);
                if (!isset($udxGroupMap[$ns])) {
                    $udxGroupMap[$ns] = [
                        'technical_name' => $groupTechName,
                        'name_de' => $this->udxNamespaceToGroupName($ns),
                        'name_en' => null,
                        'description' => "Automatisch erstellt aus UDX-Namespace {$ns}",
                        'sort_order' => 900,
                    ];
                }

                $attrTechName = $this->sanitizeTechnicalName($key);
                $udxLang = $udxField['language'] ?? null;
                $fieldIndex = $udxField['index'] ?? 0;

                // Maximalen Index pro Attribut tracken
                $udxMaxIndex[$attrTechName] = max($udxMaxIndex[$attrTechName] ?? 0, $fieldIndex);

                if (!isset($udxAttributeMap[$attrTechName])) {
                    $dataType = $this->inferDataType([$udxField['value']], null);
                    $udxAttributeMap[$attrTechName] = [
                        'technical_name' => $attrTechName,
                        'name_de' => $udxField['fieldname'],
                        'name_en' => null,
                        'description' => null,
                        'data_type' => $dataType,
                        'attribute_group' => $groupTechName,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => false,
                        'max_multiplied' => null,
                        'is_translatable' => $udxLang !== null,
                        'is_mandatory' => false,
                        'is_unique' => false,
                        'is_searchable' => true,
                        'is_inheritable' => true,
                        'parent_attribute' => null,
                        'source_system' => 'bmecat_udx',
                        'source_attribute_key' => $key,
                        'views' => null,
                    ];
                } elseif ($udxLang !== null && !$udxAttributeMap[$attrTechName]['is_translatable']) {
                    $udxAttributeMap[$attrTechName]['is_translatable'] = true;
                }

                $productValues[] = [
                    'sku' => $sku,
                    'attribute' => $attrTechName,
                    'value' => $udxField['value'],
                    'unit' => null,
                    'language' => $udxLang,
                    'index' => $fieldIndex,
                ];
            }
            // Attribute als vermehrbar markieren, wenn index > 0 vorkam
            foreach ($udxMaxIndex as $techName => $maxIdx) {
                if ($maxIdx > 0 && isset($udxAttributeMap[$techName])) {
                    $udxAttributeMap[$techName]['is_multipliable'] = true;
                    $udxAttributeMap[$techName]['max_multiplied'] = 20;
                }
            }

            // --- Preistypen + Preise ---
            foreach ($product['prices'] as $price) {
                $priceType = $price['price_type'];
                if (!isset($allPriceTypes[$priceType])) {
                    $allPriceTypes[$priceType] = [
                        'technical_name' => $priceType,
                        'name_de' => $this->translatePriceType($priceType),
                        'name_en' => $priceType,
                    ];
                }
                $country = !empty($price['territories']) ? $price['territories'][0] : null;
                $prices[] = [
                    'sku' => $sku,
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

            // --- Beziehungstypen + Beziehungen ---
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
                $relations[] = [
                    'source_sku' => $sku,
                    'target_sku' => $ref['target_id'],
                    'relation_type' => $ref['type'],
                    'sort_order' => $sortOrderRelations++,
                ];
            }

            // --- Medien ---
            foreach ($product['media'] as $idx => $m) {
                $media[] = [
                    'sku' => $sku,
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

            // --- Produkt-Zeile ---
            $productRows[] = [
                'sku' => $sku,
                'name' => $product['details']['description_short'] ?? $sku,
                'name_en' => null,
                'product_type' => $productTypeName,
                'ean' => $product['details']['ean'] ?? null,
                'status' => 'draft',
            ];

            // --- Hierarchie-Daten sammeln ---
            foreach ($product['catalog_group_maps'] as $map) {
                $skuToGroupIds[$sku][$map['group_id']] = true;
            }

            if ($hasTree) {
                $productGroupIds = array_keys($skuToGroupIds[$sku] ?? []);
                if (!empty($productGroupIds)) {
                    // nodeAttributes für Hierarchie-Attribut-Zuordnungen
                    foreach ($product['features'] as $feature) {
                        $techName = $this->sanitizeTechnicalName($feature['fname']);
                        if (empty($techName)) {
                            continue;
                        }
                        foreach ($productGroupIds as $groupId) {
                            $nodeAttributes[$groupId][$techName] = true;
                        }
                    }
                    foreach ($product['udx_fields'] as $udxField) {
                        $techName = $this->sanitizeTechnicalName($udxField['key']);
                        if (empty($techName)) {
                            continue;
                        }
                        foreach ($productGroupIds as $groupId) {
                            $nodeAttributes[$groupId][$techName] = true;
                        }
                    }
                    if (!empty($descShortI18n)) {
                        foreach ($productGroupIds as $groupId) {
                            $nodeAttributes[$groupId]['description_short'] = true;
                        }
                    }
                    if (!empty($descLongI18n)) {
                        foreach ($productGroupIds as $groupId) {
                            $nodeAttributes[$groupId]['description_long'] = true;
                        }
                    }
                }

                // Produkt-Hierarchie-Zuordnungen (inline maps)
                foreach ($product['catalog_group_maps'] as $map) {
                    $path = $this->resolveNodePath($map['group_id'], $catalogGroupTree);
                    if (!empty($path)) {
                        $nodePath = implode('/', $path);
                        $key = $sku . '|' . $nodePath;
                        if (!isset($seenHierarchy[$key])) {
                            $seenHierarchy[$key] = true;
                            $productHierarchies[] = [
                                'sku' => $sku,
                                'hierarchy' => $hierarchyTechName,
                                'node_path' => $nodePath,
                            ];
                        }
                    }
                }
            }
        }

        // Produkt-Array vollständig freigeben
        $parsed['products'] = [];
        unset($productKeys, $skuToGroupIds);

        // UDX-Attribute in die Attribut-Map einfügen (nach regulären Features)
        foreach ($udxAttributeMap as $techName => $udxAttr) {
            if (!isset($attributeMap[$techName])) {
                $attributeMap[$techName] = $udxAttr;
            }
        }
        unset($udxAttributeMap);

        // Sheets zusammenstellen
        if (!empty($udxGroupMap)) {
            $sheets['02_Attributgruppen'] = array_values($udxGroupMap);
        }
        unset($udxGroupMap);

        if (!empty($attributeMap)) {
            $sheets['05_Attribute'] = array_values($attributeMap);
        }
        unset($attributeMap);

        if (!empty($allPriceTypes)) {
            $sheets['16_Preistypen'] = array_values($allPriceTypes);
        }
        unset($allPriceTypes);

        if (!empty($allRelationTypes)) {
            $sheets['17_Beziehungstypen'] = array_values($allRelationTypes);
        }
        unset($allRelationTypes);

        // 4. Hierarchie-Attribut-Zuordnungen (aus gesammelten nodeAttributes)
        $this->sendBuildProgress('Hierarchie-Attribut-Zuordnungen', 0, $totalProducts);
        $this->checkCancelled();
        if ($hasTree && !empty($nodeAttributes)) {
            $hierarchyAttributeRows = [];
            $sort = 0;
            foreach ($nodeAttributes as $groupId => $attributes) {
                $path = $this->resolveNodePath($groupId, $catalogGroupTree);
                if (empty($path)) {
                    continue;
                }
                $nodePath = implode('/', $path);
                foreach (array_keys($attributes) as $attrTechName) {
                    $hierarchyAttributeRows[] = [
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
            if (!empty($hierarchyAttributeRows)) {
                $sheets['07_Hierarchie_Attribute'] = $hierarchyAttributeRows;
            }
            unset($hierarchyAttributeRows);
        }
        unset($nodeAttributes);

        // 5. Produkte
        $sheets['08_Produkte'] = $productRows;
        unset($productRows);

        // 6. Produktwerte
        if (!empty($productValues)) {
            $sheets['09_Produktwerte'] = $productValues;
        }
        unset($productValues);

        // 7. Produkt-Hierarchie-Zuordnungen (standalone maps ergänzen)
        if ($hasTree) {
            foreach ($parsed['product_to_group_maps'] as $map) {
                $path = $this->resolveNodePath($map['group_id'], $catalogGroupTree);
                if (!empty($path)) {
                    $nodePath = implode('/', $path);
                    $key = $map['prod_id'] . '|' . $nodePath;
                    if (!isset($seenHierarchy[$key])) {
                        $seenHierarchy[$key] = true;
                        $productHierarchies[] = [
                            'sku' => $map['prod_id'],
                            'hierarchy' => $hierarchyTechName,
                            'node_path' => $nodePath,
                        ];
                    }
                }
            }
        }
        unset($seenHierarchy);
        if (!empty($productHierarchies)) {
            $sheets['11_Produkt_Hierarchien'] = $productHierarchies;
        }
        unset($productHierarchies);

        // 8. Produktbeziehungen
        if (!empty($relations)) {
            $sheets['12_Produktbeziehungen'] = $relations;
        }
        unset($relations);

        // 9. Preise
        if (!empty($prices)) {
            $sheets['13_Preise'] = $prices;
        }
        unset($prices);

        // 10. Medien
        if (!empty($media)) {
            $sheets['14_Medien'] = $media;
        }
        unset($media);

        return new ParseResult(
            sheetsFound: array_keys($sheets),
            data: $sheets,
        );
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    /** Cache für sanitizeTechnicalName-Ergebnisse. */
    private array $sanitizeCache = [];

    /**
     * Sanitisiert einen FNAME zu einem gültigen technical_name.
     */
    public function sanitizeTechnicalName(string $name): string
    {
        if (isset($this->sanitizeCache[$name])) {
            return $this->sanitizeCache[$name];
        }

        $originalName = $name;

        // Deutsche Umlaute explizit ersetzen (vor Transliteration)
        $name = str_replace(
            ['Ä', 'ä', 'Ö', 'ö', 'Ü', 'ü', 'ß'],
            ['Ae', 'ae', 'Oe', 'oe', 'Ue', 'ue', 'ss'],
            $name,
        );

        // Restliche Sonderzeichen transliterieren
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
        $name = $transliterated !== false ? $transliterated : mb_strtolower($name);

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

        $this->sanitizeCache[$originalName] = $name;
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

    /** Cache für resolveNodePath-Ergebnisse. */
    private array $nodePathCache = [];

    /**
     * Löst den vollständigen Pfad (als Array von Knotennamen) für eine GROUP_ID auf.
     * Ergebnisse werden gecacht für bessere Performance bei vielen Produkten.
     *
     * @return string[] Pfad-Komponenten von Root abwärts (ohne Root selbst)
     */
    private function resolveNodePath(string|int $groupId, array $tree): array
    {
        $groupId = (string) $groupId;

        if (isset($this->nodePathCache[$groupId])) {
            return $this->nodePathCache[$groupId];
        }

        if (!isset($tree[$groupId])) {
            $this->nodePathCache[$groupId] = [];
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

            array_unshift($path, $node['group_name']);
            $current = $node['parent_id'];
        }

        $this->nodePathCache[$groupId] = $path;
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
        $loopIndex = 0;

        foreach ($products as $product) {
            $loopIndex++;
            if ($loopIndex % 1000 === 0) {
                $this->heartbeat();
                $this->checkCancelled();
            }

            $productGroupIds = array_keys($skuToGroupIds[$product['sku']] ?? []);
            if (empty($productGroupIds)) {
                continue;
            }

            // Feature-Attribute sammeln
            foreach ($product['features'] as $feature) {
                $techName = $this->sanitizeTechnicalName($feature['fname']);
                if (empty($techName)) {
                    continue;
                }

                foreach ($productGroupIds as $groupId) {
                    $nodeAttributes[$groupId][$techName] = true;
                }
            }

            // UDX-Attribute sammeln (gleiche Hierarchie-Zuordnung wie Features)
            foreach ($product['udx_fields'] as $udxField) {
                $techName = $this->sanitizeTechnicalName($udxField['key']);
                if (empty($techName)) {
                    continue;
                }

                foreach ($productGroupIds as $groupId) {
                    $nodeAttributes[$groupId][$techName] = true;
                }
            }

            // Beschreibungs-Attribute sammeln (description_short, description_long)
            $descShortI18n = $product['details']['description_short_i18n'] ?? [];
            $descLongI18n = $product['details']['description_long_i18n'] ?? [];
            if (!empty($descShortI18n)) {
                foreach ($productGroupIds as $groupId) {
                    $nodeAttributes[$groupId]['description_short'] = true;
                }
            }
            if (!empty($descLongI18n)) {
                foreach ($productGroupIds as $groupId) {
                    $nodeAttributes[$groupId]['description_long'] = true;
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
        $loopIndex = 0;

        // Inline-Maps (aus Produkten)
        foreach ($products as $product) {
            $loopIndex++;
            if ($loopIndex % 1000 === 0) {
                $this->heartbeat();
                $this->checkCancelled();
            }
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
            'udp_dummy' => 'Dummy-Preis (UDP)',
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
    // UDX-Hilfsmethoden
    // =========================================================================

    /**
     * Parst alle Kind-Elemente eines USER_DEFINED_EXTENSIONS-Blocks.
     *
     * Erwartet Elementnamen im Format UDX.{NAMESPACE}.{FELDNAME}.
     * Elemente ohne gültiges UDX-Muster werden geloggt und übersprungen.
     *
     * Unterstützt geschachtelte Container-Elemente (z.B. UDX.DOKA.MIME),
     * die selbst Kind-Elemente enthalten. Wiederholte Container werden
     * als vermehrbare Attribute mit multiplied_index importiert.
     *
     * @return array<array{key: string, namespace: string, fieldname: string, value: string, language: ?string, index: int}>
     */
    private function parseUdxFields(\SimpleXMLElement $udxBlock, ?string $ns): array
    {
        $fields = [];
        $seenKeys = [];

        // Kind-Elemente iterieren (mit oder ohne Namespace)
        $children = $ns ? $udxBlock->children($ns) : $udxBlock->children();

        foreach ($children as $child) {
            $this->parseUdxElement($child, $fields, $seenKeys);
        }

        // Fallback: Auch nicht-namespace-qualifizierte Kinder prüfen, wenn mit NS geparst
        if ($ns) {
            foreach ($udxBlock->children() as $child) {
                $elementName = $child->getName();
                if (!preg_match('/^UDX\.([^.]+)\.(.+)$/', $elementName, $matches)) {
                    continue;
                }
                // Nur hinzufügen wenn nicht bereits per NS gefunden
                if (isset($seenKeys[$elementName])) {
                    continue;
                }
                $this->parseUdxElement($child, $fields, $seenKeys);
            }
        }

        return $fields;
    }

    /**
     * Parst ein einzelnes UDX-Element — flach oder als Container mit Kindern.
     * Rekursiv: Container können beliebig tief geschachtelt sein.
     *
     * Bei verschachtelten Containern (z.B. MIME_INFO > MIME > MIME_SOURCE)
     * wird der innerste wiederholte Container als Index-Geber verwendet.
     * Beispiel: 2× UDX.DOKA.MIME → MIME_SOURCE bekommt index 0 und 1.
     */
    private function parseUdxElement(\SimpleXMLElement $child, array &$fields, array &$seenKeys, ?int $inheritedIndex = null): void
    {
        $elementName = $child->getName();
        if (!preg_match('/^UDX\.([^.]+)\.(.+)$/', $elementName, $matches)) {
            Log::channel('import')->warning("UDX-Element mit ungültigem Namensformat übersprungen: {$elementName}");
            return;
        }

        $parentNs = $matches[1];

        // Prüfen ob das Element Kind-Elemente hat (= Container)
        $hasChildren = $child->children()->count() > 0;

        if ($hasChildren) {
            // Container-Element (z.B. UDX.DOKA.MIME_INFO oder UDX.DOKA.MIME)
            // Index = wie oft dieser Container schon vorkam
            $containerKey = $elementName;
            $index = $seenKeys[$containerKey] ?? 0;
            $seenKeys[$containerKey] = $index + 1;

            // Der innerste wiederholte Container bestimmt den Index für Kinder.
            // Beispiel: MIME_INFO (1×, idx=0) > MIME (2×, idx=0/1) > MIME_SOURCE
            // → MIME_SOURCE bekommt idx 0 bzw. 1 vom MIME-Container
            $effectiveIndex = $index;

            // Rekursiv: Kinder verarbeiten (können selbst Container sein)
            foreach ($child->children() as $subChild) {
                $this->parseUdxElement($subChild, $fields, $seenKeys, $effectiveIndex);
            }
        } else {
            // Flaches Element (einfacher Textwert)
            $value = trim((string) $child);
            if ($value === '') {
                return;
            }

            $lang = $this->xmlLang($child);

            // Index: vom Container geerbt oder eigener Zähler
            if ($inheritedIndex !== null) {
                $index = $inheritedIndex;
            } else {
                $fieldKey = $elementName;
                $index = $seenKeys[$fieldKey] ?? 0;
                $seenKeys[$fieldKey] = $index + 1;
            }

            $fields[] = [
                'key' => $elementName,
                'namespace' => $parentNs,
                'fieldname' => $matches[2],
                'value' => $value,
                'language' => $lang,
                'index' => $index,
            ];
        }
    }

    /**
     * Leitet den Attributgruppen-Namen aus einem UDX-Namespace ab.
     *
     * Konvention: "UDX – {Namespace}" wobei der Namespace mit Großbuchstabe
     * am Anfang und Rest lowercase geschrieben wird, außer bei reinen
     * Großbuchstaben-Kürzeln (≤ 4 Zeichen), die unverändert bleiben.
     */
    private function udxNamespaceToGroupName(string $namespace): string
    {
        // Sehr kurze Kürzel (z.B. SAP, IBM) bleiben uppercase
        if (strlen($namespace) <= 3 && strtoupper($namespace) === $namespace) {
            return 'UDX – ' . $namespace;
        }

        // Sonst: Ucfirst-Lowercase (z.B. DOKA → Doka, BOSCH → Bosch)
        return 'UDX – ' . ucfirst(strtolower($namespace));
    }

    /**
     * Leitet den technischen Namen einer UDX-Attributgruppe ab.
     */
    private function udxNamespaceToGroupTechName(string $namespace): string
    {
        return 'udx_' . strtolower($namespace);
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

        if ($el === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                Log::channel('import')->warning("XML-Parsing-Fehler: " . trim($error->message) . " (Zeile {$error->line})");
            }
        }

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
     * Prüft ob ein i18n-Array mindestens einen expliziten Sprachschlüssel enthält.
     *
     * @param array<string|null, string> $i18nMap
     */
    private function hasExplicitLanguage(array $i18nMap): bool
    {
        foreach (array_keys($i18nMap) as $k) {
            // PHP wandelt null-Keys zu "" um, daher auch leere Strings ignorieren
            if ($k !== null && $k !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Liest ein XML-Attribut namespace-sicher aus.
     *
     * SimpleXML hat einen Quirk: Wenn ein Element über children($ns) geholt wurde,
     * sind unqualifizierte Attribute nicht über $element['attr'] zugänglich.
     * Über $element->attributes()['attr'] funktioniert es dagegen immer.
     */
    private function attr(\SimpleXMLElement $element, string $attrName): ?string
    {
        $value = (string) ($element->attributes()[$attrName] ?? '');

        return $value !== '' ? $value : null;
    }

    /**
     * Liest das xml:lang-Attribut eines Elements und mapped es auf PIM-Sprachcode.
     *
     * BMEcat verwendet ISO 639-2 (deu, eng, fra), PIM nutzt ISO 639-1 (de, en, fr).
     * Unterstützt sowohl xml:lang="deu" als auch plain lang="DE" (z.B. FLEX-Kataloge).
     * Gibt null zurück wenn kein lang-Attribut gesetzt ist.
     */
    private function xmlLang(\SimpleXMLElement $element): ?string
    {
        // Zuerst xml:lang prüfen (Standard BMEcat 2005)
        $lang = (string) ($element->attributes('xml', true)['lang'] ?? '');

        // Fallback: plain lang-Attribut (z.B. FLEX-Kataloge mit lang="DE")
        if ($lang === '') {
            $lang = (string) ($element->attributes()['lang'] ?? '');
        }

        if ($lang === '') {
            return null;
        }

        return $this->mapLanguageCode($lang);
    }

    /**
     * Mapped einen BMEcat-Sprachcode (ISO 639-2/B oder ISO 639-1) auf PIM-Sprachcode (ISO 639-1).
     */
    private function mapLanguageCode(string $code): string
    {
        $lower = mb_strtolower(trim($code));

        // Bereits ISO 639-1 (2 Zeichen)?
        if (strlen($lower) === 2) {
            return $lower;
        }

        return self::LANG_MAP[$lower] ?? $lower;
    }

    /**
     * Liest mehrsprachige Textinhalte eines Kind-Elements.
     *
     * Gibt ein Array mit ['language' => 'text', ...] zurück.
     * Elemente ohne xml:lang nutzen $defaultLang als Key (null = kein Sprachkey).
     *
     * @return array<string|null, string> language → text
     */
    private function textsMultilang(\SimpleXMLElement $parent, string $childName, ?string $ns, ?string $defaultLang = null): array
    {
        $result = [];

        $children = $ns
            ? $parent->children($ns)->{$childName}
            : $parent->{$childName};

        if ($children === null) {
            return $result;
        }

        foreach ($children as $child) {
            $value = trim((string) $child);
            if ($value === '') {
                continue;
            }

            $lang = $this->xmlLang($child) ?? $defaultLang;
            $result[$lang] = $value;
        }

        return $result;
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
