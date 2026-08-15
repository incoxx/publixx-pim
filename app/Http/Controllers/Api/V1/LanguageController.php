<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreLanguageRequest;
use App\Http\Requests\Api\V1\UpdateLanguageRequest;
use App\Http\Resources\Api\V1\LanguageResource;
use App\Models\Language;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Verwaltung der Inhaltssprachen.
 *
 * Diese Liste steuert, in welche Sprachen das TMS uebersetzt und welche
 * Sprachen zurueck ins PIM geschrieben werden.
 */
class LanguageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Language::class);

        $query = Language::query();
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return LanguageResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $this->authorize('create', Language::class);

        $language = Language::create($request->validated() + ['is_source' => false]);

        return (new LanguageResource($language))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Language $language): LanguageResource
    {
        $this->authorize('view', $language);

        return new LanguageResource($language);
    }

    public function update(UpdateLanguageRequest $request, Language $language): LanguageResource
    {
        $this->authorize('update', $language);

        $data = $request->validated();

        // Die Quellsprache ist der Ausgangspunkt jeder Uebersetzung — sie
        // abzuschalten wuerde den gesamten Ingest stilllegen.
        if ($language->is_source && array_key_exists('is_active', $data) && !$data['is_active']) {
            abort(422, 'Die Quellsprache kann nicht deaktiviert werden.');
        }

        $language->update($data);

        return new LanguageResource($language->fresh());
    }

    public function destroy(Language $language): JsonResponse
    {
        $this->authorize('delete', $language);

        if ($language->is_source) {
            abort(422, 'Die Quellsprache kann nicht geloescht werden.');
        }

        // Vorhandene Uebersetzungen sind echte Arbeit — teils manuell gepflegt.
        // Statt sie stillschweigend verwaisen zu lassen, blockieren wir und
        // verweisen auf Deaktivieren.
        $inUse = ProductAttributeValue::where('language', $language->technical_name)->exists();

        if ($inUse) {
            abort(422, sprintf(
                'Für "%s" existieren bereits Attributwerte. Sprache stattdessen deaktivieren, '
                . 'damit die vorhandenen Übersetzungen erhalten bleiben.',
                $language->technical_name,
            ));
        }

        $language->delete();

        return response()->json(['message' => 'Sprache gelöscht.']);
    }
}
