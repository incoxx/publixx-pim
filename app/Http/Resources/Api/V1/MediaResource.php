<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'original_file_name' => $this->original_file_name,
            'file_path' => $this->file_path,
            'file_status' => $this->file_status ?? 'ok',
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'media_type' => $this->media_type,
            'title_de' => $this->title_de,
            'title_en' => $this->title_en,
            'description_de' => $this->description_de,
            'description_en' => $this->description_en,
            'alt_text_de' => $this->alt_text_de,
            'alt_text_en' => $this->alt_text_en,
            'keywords' => $this->keywords ?? [],
            'width' => $this->width,
            'height' => $this->height,
            'asset_folder_id' => $this->asset_folder_id,
            'usage_purpose' => $this->usage_purpose,
            'last_uploaded_at' => $this->last_uploaded_at,
            'motif_id' => $this->motif_id,
            'is_master_rendition' => (bool) $this->is_master_rendition,
            'rendition_channel' => $this->rendition_channel,
            'colorspace' => $this->colorspace,
            'dpi' => $this->dpi,
            'generated_at' => $this->generated_at,
            'revision_count' => $this->revisions_count ?? 0,
            'url' => url('api/v1/media/file/' . rawurlencode($this->file_name)),
            'file_url' => url('api/v1/media/file/' . rawurlencode($this->file_name)),
            'thumb_url' => url("api/v1/media/thumb/{$this->id}?w=300&h=300"),
            'pdf_preview_url' => $this->getPdfPreviewUrl(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * URL der ersten PDF-Seite als Vorschaubild (WebP).
     * Null wenn kein PDF oder noch nicht verarbeitet.
     */
    private function getPdfPreviewUrl(): ?string
    {
        if (!$this->isPdf()) {
            return null;
        }

        $pdfDoc = $this->relationLoaded('pdfDocument')
            ? $this->pdfDocument
            : $this->pdfDocument()->first();

        if (!$pdfDoc || $pdfDoc->status !== 'ready') {
            return null;
        }

        return url("api/v1/pdf/{$pdfDoc->id}/page/1");
    }

    private function isPdf(): bool
    {
        if ($this->mime_type === 'application/pdf') {
            return true;
        }
        if ($this->file_name && str_ends_with(strtolower($this->file_name), '.pdf')) {
            return true;
        }
        return false;
    }
}
