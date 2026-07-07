<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssetCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $this->additional['lang'] ?? $request->query('lang', 'de');

        $title = $lang === 'en' && $this->title_en ? $this->title_en : $this->title_de;
        $description = $lang === 'en' && $this->description_en ? $this->description_en : $this->description_de;

        // Resolve metadata from EAV attribute values
        $metadata = [];
        if ($this->relationLoaded('attributeValues')) {
            foreach ($this->attributeValues as $attrValue) {
                $attr = $attrValue->attribute;
                if (!$attr) {
                    continue;
                }

                // Skip values with wrong language
                if ($attrValue->language !== null && $attrValue->language !== $lang) {
                    continue;
                }

                $value = $this->resolveAttributeValue($attrValue, $attr, $lang);
                if ($value === null) {
                    continue;
                }

                $metadata[] = [
                    'attribute_id' => $attr->id,
                    'attribute_name' => $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de,
                    'technical_name' => $attr->technical_name,
                    'data_type' => $attr->data_type,
                    'value' => $value,
                    'unit' => $attrValue->unit?->abbreviation,
                ];
            }
        }

        // PDF preview URL (first page WebP from pipeline)
        $pdfPreviewUrl = null;
        $isPdf = $this->mime_type === 'application/pdf'
            || str_ends_with(strtolower($this->file_name ?? ''), '.pdf');

        if ($isPdf) {
            $pdfDoc = $this->relationLoaded('pdfDocument')
                ? $this->pdfDocument
                : PdfDocument::where('media_id', $this->id)->where('status', 'ready')->first();

            if ($pdfDoc && $pdfDoc->status === 'ready') {
                $firstPage = $pdfDoc->relationLoaded('pages')
                    ? $pdfDoc->pages->where('page_number', 1)->first()
                    : $pdfDoc->pages()->where('page_number', 1)->first();

                if ($firstPage && $firstPage->image_path) {
                    $pdfPreviewUrl = Storage::disk('public')->url($firstPage->image_path);
                }
            }
        }

        // Folder breadcrumb
        $folderPath = null;
        if ($this->relationLoaded('assetFolder') && $this->assetFolder) {
            $folderPath = $lang === 'en' && $this->assetFolder->name_en
                ? $this->assetFolder->name_en
                : $this->assetFolder->name_de;
        }

        // Zugeordnete Hierarchieknoten
        $hierarchyNodes = [];
        if ($this->relationLoaded('hierarchyNodeAssignments')) {
            foreach ($this->hierarchyNodeAssignments as $assignment) {
                $node = $assignment->hierarchyNode;
                if (!$node) continue;
                $hierarchyNodes[] = [
                    'node_id' => $node->id,
                    'node_name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                    'hierarchy_name' => $lang === 'en' && $node->hierarchy?->name_en
                        ? $node->hierarchy->name_en
                        : ($node->hierarchy?->name_de ?? ''),
                    'hierarchy_type' => $node->hierarchy?->hierarchy_type,
                ];
            }
        }

        $mediaLanguage = null;
        if ($this->relationLoaded('mediaLanguage') && $this->mediaLanguage) {
            $mediaLanguage = $lang === 'en' && $this->mediaLanguage->name_en
                ? $this->mediaLanguage->name_en
                : $this->mediaLanguage->name_de;
        }

        $mediaCountry = null;
        if ($this->relationLoaded('mediaCountry') && $this->mediaCountry) {
            $mediaCountry = $lang === 'en' && $this->mediaCountry->name_en
                ? $this->mediaCountry->name_en
                : $this->mediaCountry->name_de;
        }

        $usageCount = ($this->product_assignments_count ?? 0) + ($this->hierarchy_node_assignments_count ?? 0);

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'media_type' => $this->media_type,
            'usage_purpose' => $this->usage_purpose,
            'title' => $title,
            'description' => $description,
            'alt_text' => $lang === 'en' && $this->alt_text_en ? $this->alt_text_en : $this->alt_text_de,
            'keywords' => $this->keywords ?? [],
            'media_language' => $mediaLanguage,
            'media_country' => $mediaCountry,
            'width' => $this->width,
            'height' => $this->height,
            'asset_folder_id' => $this->asset_folder_id,
            'folder_name' => $folderPath,
            'hierarchy_nodes' => $hierarchyNodes,
            'is_used' => $usageCount > 0,
            'usage_count' => $usageCount,
            'thumb_url' => url("api/v1/media/thumb/{$this->id}?w=300&h=300"),
            'preview_url' => url("api/v1/media/thumb/{$this->id}?w=800&h=800"),
            'pdf_preview_url' => $pdfPreviewUrl,
            'original_url' => url('api/v1/media/file/' . rawurlencode($this->file_name)),
            'metadata' => $metadata,
            'match_sources' => $this->match_sources ?? null,
            'relevance_score' => $this->relevance_score ?? null,
            'references' => $this->references ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveAttributeValue($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? 'Ja' : 'Nein') : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
            default => $attrValue->value_string,
        };
    }

    private function resolveSelectionValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) {
            return $attrValue->value_string;
        }
        return $lang === 'en' && $entry->display_value_en
            ? $entry->display_value_en
            : $entry->display_value_de;
    }

    private function resolveDictionaryValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) {
            return $attrValue->value_string;
        }
        return $lang === 'en' && $entry->short_text_en
            ? $entry->short_text_en
            : $entry->short_text_de;
    }
}
