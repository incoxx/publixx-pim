<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON-Transformation für Produkte im Dokumentenportal.
 *
 * Enthält Produktdaten + Zusammenfassung der verfügbaren Dokumente.
 */
class DocumentPortalProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documents = $this->media
            ->where('media_type', 'document');

        $availableLanguages = $documents
            ->pluck('language')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $documentTypes = $documents
            ->pluck('document_type')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $image = $this->media->firstWhere('media_type', 'image');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'ean' => $this->ean,
            'product_type' => $this->productType?->name_de,
            'image_url' => $image
                ? url('api/v1/media/thumb/' . $image->id . '?w=300&h=300')
                : null,
            'document_count' => $documents->count(),
            'language_count' => $availableLanguages->count(),
            'available_languages' => $availableLanguages,
            'document_types' => $documentTypes,
        ];
    }
}
