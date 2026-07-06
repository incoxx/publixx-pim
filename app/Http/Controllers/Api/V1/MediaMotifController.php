<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaMotifRequest;
use App\Http\Requests\Api\V1\UpdateMediaMotifRequest;
use App\Http\Resources\Api\V1\MediaMotifResource;
use App\Models\Media;
use App\Models\MediaMotif;
use App\Services\Media\MediaRenditionPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class MediaMotifController extends Controller
{
    /**
     * Bedingung "Motiv wird verwendet": entweder zeigt eine Produktzuordnung
     * direkt auf das Motiv, oder mindestens eine Rendition (inkl. Master) hat
     * eine aktive Produkt- oder Knoten-Zuordnung.
     */
    private function usedCondition(): \Closure
    {
        return function ($q) {
            $q->whereHas('productAssignments')
                ->orWhereHas('renditions', function ($q2) {
                    $q2->whereHas('productAssignments')->orWhereHas('hierarchyNodeAssignments');
                });
        };
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaMotif::class);

        $query = MediaMotif::with('masterRendition')
            ->withCount('renditions')
            ->withExists(['productAssignments as has_direct_assignment'])
            ->withExists(['renditions as has_rendition_assignment' => function ($q) {
                $q->whereHas('productAssignments')->orWhereHas('hierarchyNodeAssignments');
            }]);

        $this->applySearch($query, $request, [
            'title_de', 'title_en',
            'description_de', 'description_en',
            'rights_holder', 'creator', 'credit_line',
            'license_type', 'copyright_notice', 'usage_restrictions',
            'keywords',
        ]);

        $filters = $request->query('filter', []);

        if (isset($filters['is_used'])) {
            $isUsed = filter_var($filters['is_used'], FILTER_VALIDATE_BOOLEAN);
            $isUsed
                ? $query->where($this->usedCondition())
                : $query->whereNot($this->usedCondition());
        }

        if (isset($filters['license_expired'])) {
            $expired = filter_var($filters['license_expired'], FILTER_VALIDATE_BOOLEAN);
            if ($expired) {
                $query->whereNotNull('license_valid_until')->where('license_valid_until', '<', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('license_valid_until')->orWhere('license_valid_until', '>=', now());
                });
            }
        }

        if (! empty($filters['asset_folder_id'])) {
            $query->where('asset_folder_id', $filters['asset_folder_id']);
        }

        $this->applySorting($query, $request, 'created_at', 'desc');

        return MediaMotifResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /media-motifs — ein bestehendes, eigenständiges Media-Asset zum Master
     * eines neuen Motivs machen ("promote"). Die Media-Zeile bleibt für alle
     * bestehenden Zuordnungen unverändert nutzbar.
     */
    public function store(StoreMediaMotifRequest $request, MediaRenditionPipelineService $pipeline): JsonResponse
    {
        $this->authorize('create', MediaMotif::class);

        $media = Media::findOrFail($request->validated('media_id'));
        $this->authorize('update', $media);

        if ($media->motif_id !== null) {
            return response()->json([
                'message' => 'Dieses Medium gehört bereits zu einem Motiv.',
                'motif_id' => $media->motif_id,
            ], 422);
        }

        $attributes = collect($request->validated())->except('media_id')->filter(fn ($v) => $v !== null)->toArray();
        $motif = $pipeline->promoteToMotif($media, $attributes);

        return (new MediaMotifResource($motif->load('masterRendition')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MediaMotif $mediaMotif): MediaMotifResource
    {
        $this->authorize('view', $mediaMotif);

        $mediaMotif->load(['masterRendition', 'renditions' => fn ($q) => $q->orderBy('rendition_channel')]);
        $this->loadUsedFlags($mediaMotif);

        return new MediaMotifResource($mediaMotif);
    }

    public function update(UpdateMediaMotifRequest $request, MediaMotif $mediaMotif): MediaMotifResource
    {
        $this->authorize('update', $mediaMotif);

        $mediaMotif->update($request->validated());

        $fresh = $mediaMotif->fresh(['masterRendition', 'renditions']);
        $this->loadUsedFlags($fresh);

        return new MediaMotifResource($fresh);
    }

    private function loadUsedFlags(MediaMotif $mediaMotif): void
    {
        $mediaMotif->loadExists(['productAssignments as has_direct_assignment']);
        $mediaMotif->loadExists(['renditions as has_rendition_assignment' => function ($q) {
            $q->whereHas('productAssignments')->orWhereHas('hierarchyNodeAssignments');
        }]);
    }

    /**
     * DELETE /media-motifs/{media_motif} — Motiv auflösen: generierte Renditions
     * werden gelöscht (Datei + Zeile), die Master-Datei wird zu einem eigenständigen
     * Medium zurückgestuft (motif_id = NULL) statt gelöscht zu werden — bestehende
     * Produkt-/Knoten-Zuordnungen bleiben unangetastet.
     */
    public function destroy(MediaMotif $mediaMotif): JsonResponse
    {
        $this->authorize('delete', $mediaMotif);

        if ($mediaMotif->productAssignments()->exists()) {
            return response()->json([
                'message' => 'Dieses Motiv ist direkt mindestens einem Produkt zugeordnet und kann nicht aufgelöst werden.',
            ], 422);
        }

        $disk = Storage::disk('public');

        foreach ($mediaMotif->renditions()->where('is_master_rendition', false)->get() as $rendition) {
            if ($rendition->productAssignments()->exists() || $rendition->hierarchyNodeAssignments()->exists()) {
                return response()->json([
                    'message' => "Rendition \"{$rendition->rendition_channel}\" hat noch aktive Zuordnungen und kann nicht gelöscht werden.",
                ], 422);
            }
            if ($disk->exists($rendition->file_path)) {
                $disk->delete($rendition->file_path);
            }
            $rendition->delete();
        }

        $master = $mediaMotif->masterRendition()->first();
        if ($master) {
            $master->update([
                'motif_id' => null,
                'is_master_rendition' => false,
                'rendition_channel' => null,
                'rendition_preset_id' => null,
                'generated_at' => null,
            ]);
        }

        $mediaMotif->delete();

        return response()->json([
            'message' => 'Motiv aufgelöst. Master-Datei bleibt als eigenständiges Medium erhalten.',
        ]);
    }

    /**
     * POST /media-motifs/{media_motif}/generate-renditions — Renditions für alle
     * aktiven Presets (oder eine Teilmenge via preset_ids) erzeugen/aktualisieren.
     */
    public function generateRenditions(Request $request, MediaMotif $mediaMotif, MediaRenditionPipelineService $pipeline): JsonResponse
    {
        $this->authorize('update', $mediaMotif);

        $validated = $request->validate([
            'preset_ids' => 'sometimes|array',
            'preset_ids.*' => 'uuid|exists:media_rendition_presets,id',
        ]);

        $result = $pipeline->regenerateAll($mediaMotif, $validated['preset_ids'] ?? null);

        return response()->json([
            'message' => count($result['generated']).' Rendition(s) erzeugt'.(count($result['errors']) > 0 ? ', '.count($result['errors']).' fehlgeschlagen.' : '.'),
            'generated' => \App\Http\Resources\Api\V1\MediaResource::collection($result['generated']),
            'errors' => $result['errors'],
        ]);
    }
}
