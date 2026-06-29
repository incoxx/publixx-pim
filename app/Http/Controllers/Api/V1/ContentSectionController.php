<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContentSectionRequest;
use App\Http\Requests\Api\V1\UpdateContentSectionRequest;
use App\Http\Resources\Api\V1\ContentSectionResource;
use App\Models\ContentPage;
use App\Models\ContentSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sektionen (Bausteine) einer Content-Seite. Schreibrechte richten sich
 * nach der Seite (content.edit via ContentPagePolicy::update).
 */
class ContentSectionController extends Controller
{
    public function store(StoreContentSectionRequest $request, ContentPage $contentPage): JsonResponse
    {
        $this->authorize('update', $contentPage);

        $data = $request->validated();
        $data['content_page_id'] = $contentPage->id;

        // Ans Ende einsortieren, wenn keine Position angegeben.
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = (int) $contentPage->sections()
                ->where('parent_section_id', $data['parent_section_id'] ?? null)
                ->max('sort_order') + 1;
        }

        $section = ContentSection::create($data);

        return (new ContentSectionResource($section->load('sectionType')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateContentSectionRequest $request, ContentSection $contentSection): ContentSectionResource
    {
        $this->authorize('update', $contentSection->page);

        $contentSection->update($request->validated());

        return new ContentSectionResource($contentSection->fresh()->load('sectionType'));
    }

    /**
     * Sektion neu sortieren/verschachteln (Drag&Drop im Sektions-Editor).
     */
    public function move(Request $request, ContentSection $contentSection): ContentSectionResource
    {
        $this->authorize('update', $contentSection->page);

        $validated = $request->validate([
            'sort_order' => 'required|integer|min:0',
            'parent_section_id' => 'nullable|string|exists:content_sections,id',
        ]);

        // Verhindere Zyklen: eine Sektion darf nicht ihr eigenes Elter werden.
        if (($validated['parent_section_id'] ?? null) === $contentSection->id) {
            return new ContentSectionResource($contentSection);
        }

        $contentSection->update([
            'sort_order' => $validated['sort_order'],
            'parent_section_id' => $validated['parent_section_id'] ?? null,
        ]);

        return new ContentSectionResource($contentSection->fresh()->load('sectionType'));
    }

    public function destroy(ContentSection $contentSection): JsonResponse
    {
        $this->authorize('update', $contentSection->page);

        // Kind-Sektionen hängen per ON DELETE CASCADE.
        DB::transaction(fn () => $contentSection->delete());

        return response()->json(null, 204);
    }
}
