<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use ZipArchive;

class InDesignJsxWriter
{
    private const MM_TO_PT = 2.834645; // 72 / 25.4

    /**
     * Write an InDesign export ZIP for multiple products (one page per product).
     *
     * @param array[] $pages  Each entry is a resolved-elements array for one product
     */
    public function writeCombined(array $pages, array $templateJson, array $options, string $outputPath): void
    {
        $zip = new ZipArchive();
        $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $orientation = $options['page_orientation'] ?? 'portrait';
        $pageWidthMm = $orientation === 'landscape' ? 297 : 210;
        $pageHeightMm = $orientation === 'landscape' ? 210 : 297;
        $pageWidthPt = round($pageWidthMm * self::MM_TO_PT, 2);
        $pageHeightPt = round($pageHeightMm * self::MM_TO_PT, 2);

        // Collect used colors and fonts across all pages
        $usedColors = [];
        $usedFonts = [];
        $imageMap = []; // originalPath → localPath
        $imageIndex = 1;

        // Process all pages: convert elements and collect assets
        $processedPages = [];
        foreach ($pages as $pageElements) {
            $processedElements = [];
            foreach ($pageElements as $element) {
                $processed = $this->convertElement($element);
                if ($processed === null) {
                    continue;
                }

                // Collect images
                if ($processed['type'] === 'image' && !empty($element['resolvedImages'])) {
                    foreach ($element['resolvedImages'] as $imgPath) {
                        if (!file_exists($imgPath)) {
                            continue;
                        }
                        if (!isset($imageMap[$imgPath])) {
                            $ext = pathinfo($imgPath, PATHINFO_EXTENSION) ?: 'jpg';
                            $filename = sprintf('img_%04d.%s', $imageIndex++, $ext);
                            $zip->addFile($imgPath, 'assets/' . $filename);
                            $imageMap[$imgPath] = 'assets/' . $filename;
                        }
                        $processed['src'] = $imageMap[$imgPath];
                        break; // Only first valid image
                    }
                }

                // Collect colors
                foreach (['color', 'backgroundColor', 'borderColor', 'headerBg', 'headerColor', 'altRowBg'] as $colorProp) {
                    if (!empty($processed[$colorProp]) && $processed[$colorProp] !== 'transparent') {
                        $hex = $processed[$colorProp];
                        $usedColors[$hex] = ['hex' => $hex, 'name' => 'Publixx_' . ltrim($hex, '#')];
                    }
                }

                // Collect fonts
                if (!empty($processed['fontFamily'])) {
                    $usedFonts[$processed['fontFamily']] = true;
                }

                $processedElements[] = $processed;
            }
            $processedPages[] = $processedElements;
        }

        // Build data.json
        $dataJson = [
            'meta' => [
                'generator' => 'Publixx PIM',
                'version' => '2.0',
                'exported' => now()->toIso8601String(),
                'pageSize' => [
                    'widthPt' => $pageWidthPt,
                    'heightPt' => $pageHeightPt,
                ],
                'pages' => count($processedPages),
            ],
            'elements' => count($processedPages) === 1 ? $processedPages[0] : $processedPages[0],
            'records' => count($processedPages) > 1
                ? array_map(fn ($page, $i) => ['_pageIndex' => $i, '_elements' => $page], $processedPages, array_keys($processedPages))
                : null,
            'colors' => array_values($usedColors),
            'fonts' => array_keys($usedFonts),
        ];

        $zip->addFromString('data.json', json_encode($dataJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Build JSX script
        $jsxScript = $this->buildJsxScript($pageWidthPt, $pageHeightPt, count($processedPages) > 1);
        $zip->addFromString('publixx-import.jsx', $jsxScript);

        // Build README
        $readme = $this->buildReadme($templateJson, count($processedPages), count($imageMap));
        $zip->addFromString('README.txt', $readme);

        // Build mapping.json (field-to-element mapping backup)
        $mappingJson = $this->buildMappingJson($templateJson);
        $zip->addFromString('mapping.json', json_encode($mappingJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();
    }

    /**
     * Write an InDesign export ZIP for a single product.
     */
    public function write(array $resolvedElements, array $templateJson, array $options, string $outputPath): void
    {
        $this->writeCombined([$resolvedElements], $templateJson, $options, $outputPath);
    }

    /**
     * Convert a PIM template element to InDesign-compatible format.
     */
    private function convertElement(array $element): ?array
    {
        $type = $element['type'] ?? 'text';

        $base = [
            'x' => round(($element['x'] ?? 0) * self::MM_TO_PT, 2),
            'y' => round(($element['y'] ?? 0) * self::MM_TO_PT, 2),
            'w' => round(($element['width'] ?? 50) * self::MM_TO_PT, 2),
            'h' => round(($element['height'] ?? 10) * self::MM_TO_PT, 2),
            'name' => $element['label'] ?? $element['field'] ?? $element['attributeId'] ?? $type,
        ];

        $style = $element['style'] ?? [];

        return match ($type) {
            'field', 'attribute', 'text' => $this->convertTextElement($base, $element, $style),
            'image' => $this->convertImageElement($base, $element, $style),
            'variant_table', 'relation_table', 'attribute_table' => $this->convertVariantTableElement($base, $element, $style),
            'smart_table' => $this->convertSmartTableElement($base, $element, $style),
            'shape' => $this->convertShapeElement($base, $element, $style),
            default => null,
        };
    }

    private function convertTextElement(array $base, array $element, array $style): array
    {
        $fontSize = (float) ($style['fontSize'] ?? 10);

        return array_merge($base, [
            'type' => 'text',
            'content' => $element['displayValue'] ?? '',
            'fontSize' => $fontSize,
            'fontFamily' => $style['fontFamily'] ?? 'Arial',
            'fontWeight' => $this->fontWeightToNumber($style['fontWeight'] ?? 'normal'),
            'fontStyle' => $style['fontStyle'] ?? 'normal',
            'color' => $style['color'] ?? null,
            'textAlign' => $style['textAlign'] ?? 'left',
            'lineHeight' => !empty($style['lineHeight'])
                ? round($fontSize * (float) $style['lineHeight'], 2)
                : null,
            'backgroundColor' => $this->normalizeColor($style['backgroundColor'] ?? null),
            'padding' => !empty($style['padding']) ? $this->mmToPt((float) $style['padding']) : 0,
        ]);
    }

    private function convertImageElement(array $base, array $element, array $style): array
    {
        return array_merge($base, [
            'type' => 'image',
            'src' => null, // Will be set during image processing
            'fit' => 'contain',
            'backgroundColor' => $this->normalizeColor($style['backgroundColor'] ?? null),
        ]);
    }

    private function convertVariantTableElement(array $base, array $element, array $style): array
    {
        $tableData = $element['variantTableData'] ?? ['headers' => [], 'rows' => []];
        $tStyle = $element['tableStyle'] ?? [];

        // Convert rows to InDesign cell format
        $rows = [];
        foreach ($tableData['rows'] ?? [] as $row) {
            $cells = [];
            foreach ($row as $cellValue) {
                $cells[] = ['value' => (string) $cellValue, 'colspan' => 1, 'rowspan' => 1, 'skip' => false];
            }
            $rows[] = $cells;
        }

        // Convert headers to headerRows format
        $headerRows = [];
        if (!empty($tableData['headers'])) {
            $headerCells = [];
            foreach ($tableData['headers'] as $header) {
                $headerCells[] = ['value' => (string) $header, 'colspan' => 1, 'rowspan' => 1];
            }
            $headerRows[] = $headerCells;
        }

        return array_merge($base, [
            'type' => 'smartTable',
            'tableData' => [
                'headerRows' => $headerRows,
                'rows' => $rows,
            ],
            'fontSize' => (float) ($tStyle['fontSize'] ?? $style['fontSize'] ?? 8),
            'fontFamily' => $tStyle['fontFamily'] ?? $style['fontFamily'] ?? 'Arial',
            'headerBg' => $tStyle['headerBg'] ?? '#f3f4f6',
            'headerColor' => $tStyle['headerColor'] ?? '#374151',
            'headerBold' => true,
            'altRowBg' => $tStyle['alternateRowBg'] ?? '#f9fafb',
            'textColor' => $tStyle['textColor'] ?? '#374151',
            'zebraRows' => true,
            'borderColor' => $tStyle['borderColor'] ?? '#e5e7eb',
            'borderWidth' => (float) ($tStyle['borderWeight'] ?? 0.5),
            'borderVisible' => true,
            'cellPadding' => 4,
        ]);
    }

    private function convertSmartTableElement(array $base, array $element, array $style): array
    {
        $stData = $element['smartTableData'] ?? ['headerRows' => [], 'bodyRows' => [], 'columns' => []];
        $ptl = $element['ptl'] ?? [];
        $hStyle = $ptl['headerStyle'] ?? [];
        $rStyle = $ptl['rowStyle'] ?? [];

        // Header-Zeilen konvertieren
        $headerRows = [];
        foreach ($stData['headerRows'] ?? [] as $headerRow) {
            $cells = [];
            foreach ($headerRow as $cell) {
                $cells[] = [
                    'value' => (string) ($cell['label'] ?? ''),
                    'colspan' => $cell['colspan'] ?? 1,
                    'rowspan' => $cell['rowspan'] ?? 1,
                ];
            }
            $headerRows[] = $cells;
        }

        // Body-Zeilen konvertieren
        $rows = [];
        $flatCols = $stData['columns'] ?? [];
        foreach ($stData['bodyRows'] ?? [] as $row) {
            $cells = [];
            foreach ($row as $ci => $cellValue) {
                $cells[] = [
                    'value' => (string) $cellValue,
                    'colspan' => 1,
                    'rowspan' => 1,
                    'skip' => false,
                    'align' => $flatCols[$ci]['align'] ?? 'left',
                ];
            }
            $rows[] = $cells;
        }

        return array_merge($base, [
            'type' => 'smartTable',
            'tableData' => [
                'headerRows' => $headerRows,
                'rows' => $rows,
            ],
            'fontSize' => (float) ($rStyle['fontSize'] ?? $style['fontSize'] ?? 8),
            'fontFamily' => $style['fontFamily'] ?? 'Arial',
            'headerBg' => $hStyle['backgroundColor'] ?? '#f3f4f6',
            'headerColor' => $hStyle['color'] ?? '#374151',
            'headerBold' => true,
            'altRowBg' => $ptl['zebraColor'] ?? '#f9fafb',
            'textColor' => '#374151',
            'zebraRows' => $ptl['zebraStripes'] ?? true,
            'borderColor' => $ptl['borderColor'] ?? '#e5e7eb',
            'borderWidth' => 0.5,
            'borderVisible' => true,
            'cellPadding' => 4,
        ]);
    }

    private function convertShapeElement(array $base, array $element, array $style): array
    {
        return array_merge($base, [
            'type' => 'rect',
            'backgroundColor' => $this->normalizeColor($style['backgroundColor'] ?? null),
            'borderWidth' => (float) ($style['borderWidth'] ?? 0),
            'borderColor' => $style['borderColor'] ?? null,
            'borderRadius' => isset($style['borderRadius']) ? round((float) $style['borderRadius'] * 0.75, 2) : 0,
        ]);
    }

    private function mmToPt(float $mm): float
    {
        return round($mm * self::MM_TO_PT, 2);
    }

    private function fontWeightToNumber(string $weight): int
    {
        return match ($weight) {
            'bold' => 700,
            'light' => 300,
            default => 400,
        };
    }

    private function normalizeColor(?string $color): ?string
    {
        if ($color === null || $color === '' || $color === 'transparent') {
            return null;
        }

        return $color;
    }

    /**
     * Generate the InDesign ExtendScript (ES3-compatible).
     */
    private function buildJsxScript(float $pageWidthPt, float $pageHeightPt, bool $multiPage): string
    {
        $generated = now()->toIso8601String();

        return <<<'JSX_START'
// ==========================================================================
// PUBLIXX PIM — INDESIGN LAYOUT IMPORT
// ==========================================================================
JSX_START
        . "\n// Generated: {$generated}"
        . "\n// Generator: Publixx PIM (https://publixx.de)"
        . "\n// Features: Text, Images, Smart Tables, Shapes"
        . "\n// ==========================================================================\n"
        . '#targetengine "publixx_pim"' . "\n\n"
        . $this->jsxJsonPolyfill()
        . "\n\n(function() {\n\n"
        . $this->jsxConfig($pageWidthPt, $pageHeightPt)
        . "\n\n"
        . $this->jsxHelpers()
        . "\n\n"
        . $this->jsxMain($multiPage)
        . "\n\n"
        . $this->jsxDocumentCreation()
        . "\n\n"
        . $this->jsxColorManagement()
        . "\n\n"
        . $this->jsxElementCreation()
        . "\n\n"
        . $this->jsxTextFrame()
        . "\n\n"
        . $this->jsxImageFrame()
        . "\n\n"
        . $this->jsxShapes()
        . "\n\n"
        . $this->jsxTable()
        . "\n\n"
        . $this->jsxCommonProperties()
        . "\n\n"
        . $this->jsxProgress()
        . "\n\n  main();\n\n})();\n";
    }

    private function jsxJsonPolyfill(): string
    {
        return <<<'JSX'
// JSON Parser for ExtendScript (ES3)
if (typeof JSON === 'undefined') { JSON = {}; }
if (typeof JSON.parse !== 'function') {
  JSON.parse = function(text) { return eval('(' + text + ')'); };
}
if (typeof Array.isArray !== 'function') {
  Array.isArray = function(arg) { return Object.prototype.toString.call(arg) === '[object Array]'; };
}
JSX;
    }

    private function jsxConfig(float $pageWidthPt, float $pageHeightPt): string
    {
        return <<<JSX
  var CONFIG = {
    pageWidth: {$pageWidthPt},
    pageHeight: {$pageHeightPt},
    facingPages: false
  };
JSX;
    }

    private function jsxHelpers(): string
    {
        return <<<'JSX'
  function shallowCopy(obj) {
    var copy = {};
    for (var key in obj) {
      if (obj.hasOwnProperty(key)) { copy[key] = obj[key]; }
    }
    return copy;
  }
JSX;
    }

    private function jsxMain(bool $multiPage): string
    {
        if ($multiPage) {
            return <<<'JSX'
  function main() {
    var jsonFile = File.openDialog("Publixx Layout-Daten auswählen", "JSON:*.json");
    if (!jsonFile) return;

    jsonFile.open("r");
    jsonFile.encoding = "UTF-8";
    var content = jsonFile.read();
    jsonFile.close();

    var pkg;
    try { pkg = JSON.parse(content); }
    catch (e) { alert("Fehler beim Lesen der JSON-Datei:\n" + e.message); return; }

    var basePath = jsonFile.parent;

    // Multi-page: records with _elements per page
    var records = pkg.records;
    if (!records || !Array.isArray(records) || records.length === 0) {
      // Fallback: single page with elements
      records = [{ _elements: pkg.elements }];
    }

    var progressWin = createProgressWindow("Publixx PIM Import");
    progressWin.show();

    try {
      updateProgress(progressWin, 5, "Erstelle Dokument...");
      var doc = createDocument();

      updateProgress(progressWin, 10, "Erstelle Farben...");
      prepareColors(doc, pkg.colors || []);

      for (var r = 0; r < records.length; r++) {
        var record = records[r];
        var elements = record._elements || pkg.elements || [];

        var progress = 15 + (r / records.length) * 80;
        updateProgress(progressWin, progress, "Seite " + (r + 1) + " von " + records.length + "...");

        var page;
        if (r === 0) { page = doc.pages[0]; }
        else { page = doc.pages.add(LocationOptions.AT_END); }

        for (var e = 0; e < elements.length; e++) {
          createElementOnPage(doc, page, elements[e], basePath);
        }
      }

      updateProgress(progressWin, 95, "Finalisiere...");
      progressWin.close();

      alert("Import abgeschlossen!\n\n" +
            "Dokument: " + CONFIG.pageWidth + " x " + CONFIG.pageHeight + " pt\n" +
            "Seiten: " + records.length);

    } catch (e) {
      progressWin.close();
      alert("Fehler beim Import:\n" + e.message + "\n\nZeile: " + e.line);
    }
  }
JSX;
        }

        return <<<'JSX'
  function main() {
    var jsonFile = File.openDialog("Publixx Layout-Daten auswählen", "JSON:*.json");
    if (!jsonFile) return;

    jsonFile.open("r");
    jsonFile.encoding = "UTF-8";
    var content = jsonFile.read();
    jsonFile.close();

    var pkg;
    try { pkg = JSON.parse(content); }
    catch (e) { alert("Fehler beim Lesen der JSON-Datei:\n" + e.message); return; }

    if (!pkg.elements || !Array.isArray(pkg.elements)) {
      alert("Ungültige Layout-Daten in JSON-Datei.\nErwartet: elements Array");
      return;
    }

    var basePath = jsonFile.parent;

    var progressWin = createProgressWindow("Publixx PIM Import");
    progressWin.show();

    try {
      updateProgress(progressWin, 5, "Erstelle Dokument...");
      var doc = createDocument();

      updateProgress(progressWin, 10, "Erstelle Farben...");
      prepareColors(doc, pkg.colors || []);

      var page = doc.pages[0];

      for (var e = 0; e < pkg.elements.length; e++) {
        var progress = 15 + (e / pkg.elements.length) * 80;
        updateProgress(progressWin, progress, "Element " + (e + 1) + " von " + pkg.elements.length + "...");
        createElementOnPage(doc, page, pkg.elements[e], basePath);
      }

      updateProgress(progressWin, 95, "Finalisiere...");
      progressWin.close();

      alert("Import abgeschlossen!\n\n" +
            "Dokument: " + CONFIG.pageWidth + " x " + CONFIG.pageHeight + " pt\n" +
            "Elemente: " + pkg.elements.length);

    } catch (e) {
      progressWin.close();
      alert("Fehler beim Import:\n" + e.message + "\n\nZeile: " + e.line);
    }
  }
JSX;
    }

    private function jsxDocumentCreation(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // DOCUMENT CREATION
  // ==========================================================================

  function createDocument() {
    try { app.scriptPreferences.measurementUnit = MeasurementUnits.POINTS; } catch (e) {}

    var doc = app.documents.add({
      documentPreferences: {
        pageWidth: CONFIG.pageWidth + " pt",
        pageHeight: CONFIG.pageHeight + " pt",
        facingPages: CONFIG.facingPages,
        pagesPerDocument: 1
      }
    });

    doc.viewPreferences.horizontalMeasurementUnits = MeasurementUnits.POINTS;
    doc.viewPreferences.verticalMeasurementUnits = MeasurementUnits.POINTS;

    try {
      var mp = doc.pages[0].marginPreferences;
      mp.top = 0; mp.bottom = 0; mp.left = 0; mp.right = 0;
    } catch (e) {}

    return doc;
  }
JSX;
    }

    private function jsxColorManagement(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // COLOR MANAGEMENT
  // ==========================================================================

  function prepareColors(doc, colors) {
    for (var i = 0; i < colors.length; i++) {
      createColorFromHex(doc, colors[i].hex, colors[i].name);
    }
    createColorFromHex(doc, "#333333", "Publixx_Dark");
    createColorFromHex(doc, "#F5F5F5", "Publixx_Light");
    createColorFromHex(doc, "#FFFFFF", "Publixx_White");
  }

  function createColorFromHex(doc, hex, name) {
    if (!hex) return null;
    hex = hex.replace("#", "");
    var r = parseInt(hex.substr(0, 2), 16);
    var g = parseInt(hex.substr(2, 2), 16);
    var b = parseInt(hex.substr(4, 2), 16);
    var colorName = name || ("Publixx_" + hex.toUpperCase());

    try {
      var existing = doc.colors.itemByName(colorName);
      if (existing.isValid) return existing;
    } catch (e) {}

    try {
      return doc.colors.add({
        name: colorName, model: ColorModel.PROCESS,
        space: ColorSpace.RGB, colorValue: [r, g, b]
      });
    } catch (e) {
      return doc.colors.itemByName("Black");
    }
  }

  function getColor(doc, hexOrName) {
    if (!hexOrName || hexOrName === "transparent" || hexOrName === "none") {
      try { return doc.swatches.itemByName("None"); } catch (e) { return null; }
    }
    try {
      var byName = doc.colors.itemByName(hexOrName);
      if (byName.isValid) return byName;
    } catch (e) {}
    if (hexOrName.charAt(0) === "#") { return createColorFromHex(doc, hexOrName); }
    try { return doc.colors.itemByName("Black"); } catch (e) { return null; }
  }

  function setFillColor(obj, doc, colorValue) {
    try {
      if (!colorValue || colorValue === "transparent" || colorValue === "none") {
        obj.fillColor = doc.swatches.itemByName("None");
      } else {
        obj.fillColor = getColor(doc, colorValue);
      }
    } catch (e) {
      try { obj.fillColor = doc.swatches.itemByName("None"); } catch (e2) {}
    }
  }

  function setStrokeColor(obj, doc, colorValue) {
    try {
      if (!colorValue || colorValue === "transparent" || colorValue === "none") {
        obj.strokeColor = doc.swatches.itemByName("None");
      } else {
        obj.strokeColor = getColor(doc, colorValue);
      }
    } catch (e) {}
  }
JSX;
    }

    private function jsxElementCreation(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // ELEMENT CREATION
  // ==========================================================================

  function createElementOnPage(doc, page, el, basePath) {
    if (!el || el.visible === false) return null;

    var bounds = [
      el.y || 0,
      el.x || 0,
      (el.y || 0) + (el.h || 100),
      (el.x || 0) + (el.w || 100)
    ];

    var frame = null;

    switch (el.type) {
      case "text":
      case "textbox":
        frame = createTextFrame(doc, page, el, bounds);
        break;
      case "image":
      case "img":
        frame = createImageFrame(doc, page, el, bounds, basePath);
        break;
      case "rect":
      case "rectangle":
        frame = createRectangle(doc, page, el, bounds);
        break;
      case "ellipse":
      case "oval":
        frame = createOval(doc, page, el, bounds);
        break;
      case "line":
        frame = createLine(doc, page, el);
        break;
      case "smartTable":
      case "table":
        frame = createTable(doc, page, el, bounds);
        break;
      default:
        frame = createRectangle(doc, page, el, bounds);
        break;
    }

    if (frame && frame.isValid) {
      applyCommonProperties(doc, frame, el);
    }

    return frame;
  }
JSX;
    }

    private function jsxTextFrame(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // TEXT FRAME
  // ==========================================================================

  function applyFont(textObj, fontFamily, fontStyle) {
    try {
      if (fontFamily) { textObj.appliedFont = fontFamily; }
      if (fontStyle && fontStyle !== "Regular") {
        try { textObj.fontStyle = fontStyle; }
        catch (e) {
          if (fontStyle === "Bold") {
            var alts = ["Bold", "Black", "Heavy", "Medium", "Semibold"];
            for (var i = 0; i < alts.length; i++) {
              try { textObj.fontStyle = alts[i]; break; } catch (e2) {}
            }
          }
        }
      }
    } catch (e) {
      try { textObj.appliedFont = "Arial"; } catch (e3) {}
    }
  }

  function createTextFrame(doc, page, el, bounds) {
    var tf = page.textFrames.add({ geometricBounds: bounds });

    if (el.backgroundColor && el.backgroundColor !== "transparent") {
      setFillColor(tf, doc, el.backgroundColor);
    } else {
      setFillColor(tf, doc, null);
    }

    tf.contents = String(el.content || el.text || el.value || "");

    if (tf.parentStory) {
      var story = tf.parentStory;

      if (el.fontSize) { story.pointSize = el.fontSize; }

      var fontStyle = "Regular";
      var fw = el.fontWeight;
      if (typeof fw === "string") fw = parseInt(fw, 10) || 400;
      if (fw >= 600) { fontStyle = "Bold"; }
      else if (fw <= 300) { fontStyle = "Light"; }
      else if (el.fontStyle === "italic") { fontStyle = "Italic"; }

      applyFont(story, el.fontFamily, fontStyle);

      if (el.color) {
        try { story.fillColor = getColor(doc, el.color); } catch (e) {}
      }

      if (el.lineHeight) { story.leading = el.lineHeight; }

      if (el.textAlign) {
        switch (el.textAlign) {
          case "center": story.justification = Justification.CENTER_ALIGN; break;
          case "right": story.justification = Justification.RIGHT_ALIGN; break;
          case "justify": story.justification = Justification.FULLY_JUSTIFIED; break;
          default: story.justification = Justification.LEFT_ALIGN;
        }
      }
    }

    if (el.verticalAlign) {
      switch (el.verticalAlign) {
        case "center": case "middle":
          tf.textFramePreferences.verticalJustification = VerticalJustification.CENTER_ALIGN; break;
        case "bottom":
          tf.textFramePreferences.verticalJustification = VerticalJustification.BOTTOM_ALIGN; break;
        default:
          tf.textFramePreferences.verticalJustification = VerticalJustification.TOP_ALIGN;
      }
    }

    if (el.padding) {
      var p = typeof el.padding === "number" ? el.padding : 0;
      tf.textFramePreferences.insetSpacing = [p, p, p, p];
    }

    return tf;
  }
JSX;
    }

    private function jsxImageFrame(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // IMAGE FRAME
  // ==========================================================================

  function createImageFrame(doc, page, el, bounds, basePath) {
    var rect = page.rectangles.add({ geometricBounds: bounds });

    if (el.backgroundColor && el.backgroundColor !== "transparent") {
      setFillColor(rect, doc, el.backgroundColor);
    } else {
      rect.strokeWeight = 0;
      try { rect.strokeColor = doc.swatches.item("None"); } catch (e) {}
    }

    var imagePath = el.src || el.url || el.image;
    if (imagePath) {
      var imgFile;
      if (imagePath.indexOf("assets/") === 0) {
        imgFile = new File(basePath + "/" + imagePath);
      } else if (imagePath.indexOf("/") === 0 || imagePath.indexOf(":") > 0) {
        imgFile = new File(imagePath);
      } else {
        imgFile = new File(basePath + "/" + imagePath);
      }

      if (imgFile && imgFile.exists) {
        try {
          rect.place(imgFile);
          var fitMode = el.fit || "contain";
          if (fitMode === "cover" || fitMode === "fill") {
            rect.fit(FitOptions.FILL_PROPORTIONALLY);
          } else {
            rect.fit(FitOptions.PROPORTIONALLY);
            rect.fit(FitOptions.CENTER_CONTENT);
          }
        } catch (e) {
          $.writeln("Bild-Fehler: " + e.message);
        }
      }
    }

    if (!rect.allGraphics.length) {
      rect.contentType = ContentType.GRAPHIC_TYPE;
    }

    return rect;
  }
JSX;
    }

    private function jsxShapes(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // SHAPES
  // ==========================================================================

  function createRectangle(doc, page, el, bounds) {
    var rect = page.rectangles.add({ geometricBounds: bounds });
    setFillColor(rect, doc, el.backgroundColor || el.fill);

    if (el.borderWidth || el.strokeWidth) {
      rect.strokeWeight = el.borderWidth || el.strokeWidth || 0;
      setStrokeColor(rect, doc, el.borderColor || el.stroke || "#000000");
    } else {
      rect.strokeWeight = 0;
    }

    if (el.borderRadius || el.cornerRadius) {
      var r = el.borderRadius || el.cornerRadius;
      rect.topLeftCornerRadius = r;
      rect.topRightCornerRadius = r;
      rect.bottomLeftCornerRadius = r;
      rect.bottomRightCornerRadius = r;
    }

    return rect;
  }

  function createOval(doc, page, el, bounds) {
    var oval = page.ovals.add({ geometricBounds: bounds });
    setFillColor(oval, doc, el.backgroundColor || el.fill);

    if (el.borderWidth || el.strokeWidth) {
      oval.strokeWeight = el.borderWidth || el.strokeWidth;
      setStrokeColor(oval, doc, el.borderColor || el.stroke || "#000000");
    } else {
      oval.strokeWeight = 0;
    }

    return oval;
  }

  function createLine(doc, page, el) {
    var x1 = el.x || 0;
    var y1 = el.y || 0;
    var x2 = el.x2 || (x1 + (el.w || 100));
    var y2 = el.y2 || (y1 + (el.h || 0));

    var line = page.graphicLines.add({
      geometricBounds: [
        Math.min(y1, y2), Math.min(x1, x2),
        Math.max(y1, y2), Math.max(x1, x2)
      ]
    });

    line.paths[0].entirePath = [[x1, y1], [x2, y2]];
    line.strokeWeight = el.strokeWidth || el.borderWidth || 1;
    line.strokeColor = getColor(doc, el.stroke || el.color || "#000000");

    return line;
  }
JSX;
    }

    private function jsxTable(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // SMART TABLE
  // ==========================================================================

  function createTable(doc, page, el, bounds) {
    var tf = page.textFrames.add({ geometricBounds: bounds });
    tf.contents = "";

    var tableData = el.tableData || el.data || {};
    var headerRows = tableData.headerRows || [];

    // Fallback: flat headers array
    if (headerRows.length === 0 && tableData.headers && tableData.headers.length > 0) {
      var converted = [];
      for (var hi = 0; hi < tableData.headers.length; hi++) {
        var h = tableData.headers[hi];
        converted.push(typeof h === "object" ? h : { value: h });
      }
      headerRows = [converted];
    }

    var dataRows = tableData.rows || [];

    if (headerRows.length === 0 && dataRows.length === 0) {
      tf.contents = "(Keine Tabellendaten)";
      return tf;
    }

    // Determine column count
    var allRows = headerRows.concat(dataRows);
    var maxCols = 0;
    for (var ri = 0; ri < allRows.length; ri++) {
      var row = allRows[ri];
      if (!Array.isArray(row)) continue;
      var cols = 0;
      for (var ci = 0; ci < row.length; ci++) {
        var cell = row[ci];
        cols += (typeof cell === "object" && cell.colspan) ? cell.colspan : 1;
      }
      if (cols > maxCols) maxCols = cols;
    }
    if (maxCols === 0) maxCols = 1;

    var totalRows = allRows.length;

    // Build grid
    var grid = [];
    for (var r = 0; r < totalRows; r++) {
      grid[r] = [];
      for (var c = 0; c < maxCols; c++) { grid[r][c] = null; }
    }

    var mergeQueue = [];

    for (var rowIdx = 0; rowIdx < allRows.length; rowIdx++) {
      var rowData = allRows[rowIdx];
      if (!Array.isArray(rowData)) continue;
      var isHeader = rowIdx < headerRows.length;

      if (isHeader) {
        var gridCol = 0;
        for (var cellIdx = 0; cellIdx < rowData.length; cellIdx++) {
          while (gridCol < maxCols && grid[rowIdx][gridCol] !== null) { gridCol++; }
          if (gridCol >= maxCols) break;

          var cellData = rowData[cellIdx];
          var cellValue = "", rowspan = 1, colspan = 1;
          if (typeof cellData === "object") {
            cellValue = cellData.value !== undefined ? cellData.value : (cellData.label || "");
            rowspan = cellData.rowspan || 1;
            colspan = cellData.colspan || 1;
          } else { cellValue = String(cellData || ""); }

          grid[rowIdx][gridCol] = { value: cellValue, isHeader: true, isMergeOrigin: (rowspan > 1 || colspan > 1) };

          if (rowspan > 1 || colspan > 1) {
            mergeQueue.push({ startRow: rowIdx, startCol: gridCol,
              endRow: Math.min(rowIdx + rowspan - 1, totalRows - 1),
              endCol: Math.min(gridCol + colspan - 1, maxCols - 1)
            });
            for (var sr = rowIdx; sr < rowIdx + rowspan && sr < totalRows; sr++) {
              for (var sc = gridCol; sc < gridCol + colspan && sc < maxCols; sc++) {
                if (sr === rowIdx && sc === gridCol) continue;
                grid[sr][sc] = { skip: true, isHeader: sr < headerRows.length };
              }
            }
          }
          gridCol += colspan;
        }
      } else {
        for (var colIdx = 0; colIdx < rowData.length && colIdx < maxCols; colIdx++) {
          var cellData2 = rowData[colIdx];
          if (typeof cellData2 === "object" && cellData2.skip) {
            grid[rowIdx][colIdx] = { skip: true, isHeader: false };
            continue;
          }
          var cv = "", rs = 1, cs = 1;
          if (typeof cellData2 === "object") {
            cv = cellData2.value !== undefined ? cellData2.value : "";
            rs = cellData2.rowspan || 1;
            cs = cellData2.colspan || 1;
          } else { cv = String(cellData2 || ""); }

          grid[rowIdx][colIdx] = { value: cv, isHeader: false, isMergeOrigin: (rs > 1 || cs > 1) };
          if (rs > 1 || cs > 1) {
            mergeQueue.push({ startRow: rowIdx, startCol: colIdx,
              endRow: Math.min(rowIdx + rs - 1, totalRows - 1),
              endCol: Math.min(colIdx + cs - 1, maxCols - 1)
            });
          }
        }
      }
    }

    // Create InDesign table
    var table = tf.insertionPoints[0].tables.add({
      bodyRowCount: totalRows, columnCount: maxCols
    });

    var frameWidth = bounds[3] - bounds[1];
    var colWidth = (frameWidth - 4) / maxCols;
    for (var c = 0; c < maxCols; c++) {
      try { table.columns[c].width = colWidth; } catch (e) {}
    }

    // Fill cells
    for (var r = 0; r < totalRows; r++) {
      for (var c = 0; c < maxCols; c++) {
        var info = grid[r][c];
        if (!info || info.skip) continue;
        try {
          var tc = table.rows[r].cells[c];
          tc.contents = String(info.value || "");

          try {
            if (el.fontFamily) { tc.texts[0].appliedFont = el.fontFamily; }
            if (el.fontSize) { tc.texts[0].pointSize = el.fontSize; }
          } catch (fe) {}

          if (info.isHeader) {
            setFillColor(tc, doc, el.headerBg || "#f3f4f6");
            try {
              tc.texts[0].fillColor = getColor(doc, el.headerColor || "#374151");
              if (el.headerBold !== false) { tc.texts[0].fontStyle = "Bold"; }
            } catch (e2) {}
          } else {
            if (el.textColor) {
              try { tc.texts[0].fillColor = getColor(doc, el.textColor); } catch (e3) {}
            }
            if ((r - headerRows.length) % 2 === 1 && el.zebraRows !== false) {
              setFillColor(tc, doc, el.altRowBg || "#f9fafb");
            }
          }
        } catch (e) {}
      }
    }

    // Cell padding
    var pV = el.cellPaddingV || el.cellPadding || 4;
    var pH = el.cellPaddingH || el.cellPadding || 4;
    for (var ri = 0; ri < table.rows.length; ri++) {
      for (var ci = 0; ci < table.columns.length; ci++) {
        try {
          var pc = table.rows[ri].cells[ci];
          pc.topInset = pV; pc.bottomInset = pV;
          pc.leftInset = pH; pc.rightInset = pH;
        } catch (e) {}
      }
    }

    // Apply merges (bottom-right to top-left)
    mergeQueue.sort(function(a, b) {
      if (b.startRow !== a.startRow) return b.startRow - a.startRow;
      return b.startCol - a.startCol;
    });
    for (var m = 0; m < mergeQueue.length; m++) {
      var mg = mergeQueue[m];
      try {
        table.rows[mg.startRow].cells[mg.startCol].merge(table.rows[mg.endRow].cells[mg.endCol]);
      } catch (e) {}
    }

    // Border styling
    if (el.borderVisible !== false && el.borderColor) {
      try {
        table.strokeColor = getColor(doc, el.borderColor);
        table.strokeWeight = el.borderWidth || 0.5;
      } catch (e) {}
    }

    return tf;
  }
JSX;
    }

    private function jsxCommonProperties(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // COMMON PROPERTIES
  // ==========================================================================

  function applyCommonProperties(doc, frame, el) {
    if (el.rotation || el.rotate) {
      frame.rotationAngle = el.rotation || el.rotate;
    }
    if (el.opacity !== undefined && el.opacity < 1) {
      frame.transparencySettings.blendingSettings.opacity = el.opacity * 100;
    }
    if (el.name || el.id) {
      frame.name = el.name || el.id;
    }
  }
JSX;
    }

    private function jsxProgress(): string
    {
        return <<<'JSX'
  // ==========================================================================
  // PROGRESS WINDOW
  // ==========================================================================

  function createProgressWindow(title) {
    var win = new Window("palette", title, undefined, { closeButton: false });
    win.orientation = "column";
    win.alignChildren = ["fill", "top"];
    win.add("statictext", undefined, "Importiere Layout...");
    win.progressBar = win.add("progressbar", undefined, 0, 100);
    win.progressBar.preferredSize.width = 350;
    win.statusText = win.add("statictext", undefined, "", { characters: 50 });
    return win;
  }

  function updateProgress(win, percent, text) {
    try {
      win.progressBar.value = percent;
      win.statusText.text = text || "";
      win.update();
    } catch (e) {}
  }
JSX;
    }

    /**
     * Generate README.txt content.
     */
    private function buildReadme(array $templateJson, int $pageCount, int $imageCount): string
    {
        $templateName = $templateJson['name'] ?? 'Publixx Template';
        $elementCount = count($templateJson['elements'] ?? []);

        return <<<READMETXT
================================================================================
PUBLIXX PIM — INDESIGN LAYOUT IMPORT
================================================================================

Dieses Paket wurde mit Publixx PIM erstellt und enthält ein komplettes
Layout für Adobe InDesign. Kein vorbereitetes InDesign-Template nötig!

Template: {$templateName}
Seiten: {$pageCount}
Elemente pro Seite: {$elementCount}
Bilder: {$imageCount}
Erstellt: {$this->now()}

--------------------------------------------------------------------------------
VERWENDUNG
--------------------------------------------------------------------------------

1. Adobe InDesign CC 2019 oder neuer öffnen (KEIN Dokument nötig!)

2. Script ausführen:
   - Fenster > Hilfsprogramme > Skripte
   - Doppelklick auf "publixx-import.jsx"

3. "data.json" aus diesem Ordner auswählen

4. Fertig! Das Layout wird automatisch erstellt:
   - Dokument mit korrekter Seitengröße
   - Alle Elemente positioniert (Text, Bilder, Tabellen, Formen)

--------------------------------------------------------------------------------
ENTHALTENE DATEIEN
--------------------------------------------------------------------------------

publixx-import.jsx  — InDesign Import-Script (ExtendScript)
data.json           — Layout-Daten (Elemente, Farben, Schriften)
mapping.json        — Feld-Zuordnung (Backup/Referenz)
assets/             — Heruntergeladene Produktbilder
README.txt          — Diese Datei

--------------------------------------------------------------------------------
SUPPORT
--------------------------------------------------------------------------------

Generiert mit Publixx PIM — https://publixx.de

================================================================================

READMETXT;
    }

    /**
     * Build mapping.json — field-to-element mapping for reference/backup.
     */
    private function buildMappingJson(array $templateJson): array
    {
        $mapping = [];

        foreach ($templateJson['elements'] ?? [] as $index => $element) {
            $type = $element['type'] ?? 'unknown';
            $entry = [
                'index' => $index,
                'type' => $type,
                'label' => $element['label'] ?? null,
            ];

            if ($type === 'field') {
                $entry['field'] = $element['field'] ?? null;
            } elseif ($type === 'attribute') {
                $entry['attributeId'] = $element['attributeId'] ?? null;
                $entry['technicalName'] = $element['technical_name'] ?? null;
            } elseif ($type === 'image') {
                $entry['source'] = $element['source'] ?? 'primary';
            } elseif ($type === 'variant_table') {
                $entry['columns'] = $element['columns'] ?? [];
            }

            $mapping[] = $entry;
        }

        return [
            'generator' => 'Publixx PIM',
            'exported' => now()->toIso8601String(),
            'templateName' => $templateJson['name'] ?? null,
            'elements' => $mapping,
        ];
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
