<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        // Folder breadcrumb
        $folderPath = null;
        if ($this->relationLoaded('assetFolder') && $this->assetFolder) {
            $folderPath = $lang === 'en' && $this->assetFolder->name_en
                ? $this->assetFolder->name_en
                : $this->assetFolder->name_de;
        }

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
            'width' => $this->width,
            'height' => $this->height,
            'asset_folder_id' => $this->asset_folder_id,
            'folder_name' => $folderPath,
            'thumb_url' => url("api/v1/media/thumb/{$this->id}?w=300&h=300"),
            'preview_url' => url("api/v1/media/thumb/{$this->id}?w=800&h=800"),
            'original_url' => url('api/v1/media/file/' . rawurlencode($this->file_name)),
            'metadata' => $metadata,
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
            'Selection', 'Dictionary' => $this->resolveSelectionValue($attrValue, $lang),
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
}
