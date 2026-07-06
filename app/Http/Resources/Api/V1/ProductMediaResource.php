<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Traits\ChecksInstanceRestrictions;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\RoleEntityRestriction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class ProductMediaResource extends JsonResource
{
    use ChecksInstanceRestrictions;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'media_id' => $this->media_id,
            'media' => new MediaResource($this->whenLoaded('media')),
            'motif_id' => $this->motif_id,
            'motif' => new MediaMotifResource($this->whenLoaded('motif')),
            'preview_thumb_url' => $this->previewThumbUrl(),
            'usage_type_id' => $this->usage_type_id,
            'usage_type' => new MediaUsageTypeResource($this->whenLoaded('usageType')),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
            'can_download' => $this->canDownload($request),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Ob der aktuelle Nutzer dieses Medium herunterladen darf (Rechteverwaltung pro Medientyp).
     * Fehlt die usageType-Relation, obwohl usage_type_id gesetzt ist, wird sicherheitshalber
     * verweigert (statt stillschweigend zu erlauben) — ein fehlendes Eager-Load ist ein
     * Programmierfehler, kein Grund für vollen Zugriff.
     */
    private function canDownload(Request $request): bool
    {
        $user = $request->user();
        if (! $user || $user->hasRole('Admin')) {
            return true;
        }
        if (! $this->usage_type_id) {
            return true;
        }
        if (! $this->relationLoaded('usageType') || ! $this->usageType) {
            \Log::warning('ProductMediaResource::canDownload ohne eager-geladenen usageType aufgerufen', [
                'assignment_id' => $this->id,
                'usage_type_id' => $this->usage_type_id,
            ]);

            return false;
        }

        return $this->checkInstanceAccess($user, $this->usageType, 'read');
    }

    /**
     * Vorschaubild unabhängig davon, ob eine einzelne Datei oder ein Motiv
     * zugeordnet ist (dann die Master-Rendition).
     */
    private function previewThumbUrl(): ?string
    {
        if ($this->relationLoaded('media') && $this->media) {
            return $this->thumbUrlFor($this->media);
        }

        if ($this->relationLoaded('motif') && $this->motif?->relationLoaded('masterRendition') && $this->motif->masterRendition) {
            return $this->thumbUrlFor($this->motif->masterRendition);
        }

        return null;
    }

    /**
     * Liefert eine signierte Thumb-URL, wenn der UsageType dieser Zuordnung
     * irgendwo per RoleEntityRestriction eingeschränkt ist — die öffentliche
     * media/thumb-Route verlangt in diesem Fall eine gültige Signatur
     * (siehe MediaController::assertSignedIfRestrictionSensitive).
     * Ohne aktive Restriktion bleibt die URL unsigniert (unverändertes Verhalten).
     */
    private function thumbUrlFor(Media $media): ?string
    {
        if ($this->requiresSignedUrl()) {
            return URL::temporarySignedRoute('media.thumb', now()->addHours(2), [
                'medium' => $media->id,
                'w' => 300,
                'h' => 300,
            ]);
        }

        return (new MediaResource($media))->toArray(request())['thumb_url'] ?? null;
    }

    private function requiresSignedUrl(): bool
    {
        if (! $this->relationLoaded('usageType') || ! $this->usageType) {
            return false;
        }

        return RoleEntityRestriction::where('restrictable_type', MediaUsageType::class)
            ->where('restrictable_id', $this->usageType->id)
            ->exists();
    }
}
