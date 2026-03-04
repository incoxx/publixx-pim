<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TranslationXliffController extends Controller
{
    /**
     * GET /translations/xliff/export
     *
     * Query params:
     *   - source_lang (required): e.g. "de"
     *   - target_lang (required): e.g. "en"
     *   - product_ids (optional): comma-separated product UUIDs
     */
    public function export(Request $request): Response
    {
        $request->validate([
            'source_lang' => 'required|string|max:5',
            'target_lang' => 'required|string|max:5',
            'product_ids' => 'nullable|string',
        ]);

        $sourceLang = $request->query('source_lang');
        $targetLang = $request->query('target_lang');
        $productIds = $request->query('product_ids')
            ? explode(',', $request->query('product_ids'))
            : null;

        // Get all translatable attributes
        $translatableAttrIds = Attribute::where('is_translatable', true)->pluck('id');
        if ($translatableAttrIds->isEmpty()) {
            return $this->xliffResponse($sourceLang, $targetLang, '', 'translations.xliff');
        }

        // Build query for source values
        $query = ProductAttributeValue::with(['product', 'attribute'])
            ->where('language', $sourceLang)
            ->whereIn('attribute_id', $translatableAttrIds)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '');

        if ($productIds) {
            $query->whereIn('product_id', $productIds);
        }

        $sourceValues = $query->orderBy('product_id')->orderBy('attribute_id')->get();

        // Load existing target values for comparison
        $targetValues = ProductAttributeValue::where('language', $targetLang)
            ->whereIn('attribute_id', $translatableAttrIds)
            ->when($productIds, fn ($q) => $q->whereIn('product_id', $productIds))
            ->get()
            ->keyBy(fn ($v) => $v->product_id . '_' . $v->attribute_id . '_' . $v->multiplied_index);

        // Build XLIFF 1.2
        $units = '';
        foreach ($sourceValues as $sv) {
            $key = $sv->product_id . '_' . $sv->attribute_id . '_' . $sv->multiplied_index;
            $target = $targetValues->get($key);
            $targetStr = $target?->value_string ?? '';
            $state = $targetStr !== '' ? 'translated' : 'needs-translation';

            $id = htmlspecialchars("{$sv->product_id}|{$sv->attribute_id}|{$sv->multiplied_index}", ENT_XML1);
            $sku = htmlspecialchars($sv->product?->sku ?? '', ENT_XML1);
            $attrName = htmlspecialchars($sv->attribute?->technical_name ?? '', ENT_XML1);
            $sourceXml = htmlspecialchars($sv->value_string, ENT_XML1);
            $targetXml = htmlspecialchars($targetStr, ENT_XML1);

            $units .= <<<XML
      <trans-unit id="{$id}" resname="{$sku}:{$attrName}">
        <source xml:lang="{$sourceLang}">{$sourceXml}</source>
        <target xml:lang="{$targetLang}" state="{$state}">{$targetXml}</target>
        <note from="PIM">SKU: {$sku} | Attribute: {$attrName}</note>
      </trans-unit>

XML;
        }

        $filename = "pim-translations-{$sourceLang}-{$targetLang}.xliff";

        return $this->xliffResponse($sourceLang, $targetLang, $units, $filename);
    }

    /**
     * POST /translations/xliff/import
     *
     * Body: XLIFF file upload (field: "file")
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        // Parse XLIFF
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return response()->json(['message' => 'Invalid XLIFF file.'], 422);
        }

        // Register namespace for XPath
        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        $file = $xml->file ?? null;
        if (!$file) {
            return response()->json(['message' => 'No <file> element found in XLIFF.'], 422);
        }

        $targetLang = (string) ($file['target-language'] ?? '');
        if (!$targetLang) {
            return response()->json(['message' => 'Missing target-language attribute.'], 422);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($file, $targetLang, &$imported, &$skipped, &$errors) {
            $body = $file->body ?? $file;
            $transUnits = $body->xpath('.//xliff:trans-unit') ?: $body->xpath('.//trans-unit') ?: [];

            foreach ($transUnits as $unit) {
                $id = (string) ($unit['id'] ?? '');
                $parts = explode('|', $id);

                if (count($parts) !== 3) {
                    $skipped++;
                    $errors[] = "Invalid trans-unit ID: {$id}";
                    continue;
                }

                [$productId, $attributeId, $multipliedIndex] = $parts;

                $target = (string) ($unit->target ?? '');
                if ($target === '') {
                    $skipped++;
                    continue;
                }

                // Verify product and attribute exist
                $product = Product::find($productId);
                $attribute = Attribute::find($attributeId);
                if (!$product || !$attribute || !$attribute->is_translatable) {
                    $skipped++;
                    $errors[] = "Product or attribute not found for ID: {$id}";
                    continue;
                }

                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'attribute_id' => $attributeId,
                        'language' => $targetLang,
                        'multiplied_index' => (int) $multipliedIndex,
                    ],
                    [
                        'value_string' => $target,
                        'is_inherited' => false,
                        'inherited_from_node_id' => null,
                        'inherited_from_product_id' => null,
                    ]
                );

                $imported++;
            }
        });

        return response()->json([
            'message' => "XLIFF import completed.",
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
        ]);
    }

    private function xliffResponse(string $sourceLang, string $targetLang, string $units, string $filename): Response
    {
        $xliff = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
  <file source-language="{$sourceLang}" target-language="{$targetLang}" datatype="plaintext" original="publixx-pim">
    <body>
{$units}    </body>
  </file>
</xliff>
XML;

        return response($xliff, 200, [
            'Content-Type' => 'application/xliff+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
