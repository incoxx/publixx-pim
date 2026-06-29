<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreContentSectionRequest;
use App\Http\Requests\Api\V1\UpdateContentSectionRequest;
use App\Http\Resources\Api\V1\ContentSectionResource;
use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Services\Content\ContentDuplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * Sektion (inkl. Kind-Sektionen) in diese Seite einfügen — Copy & Paste,
     * auch seitenübergreifend. source_section_id = kopierte Quelle.
     */
    public function paste(Request $request, ContentPage $contentPage, ContentDuplicator $duplicator): JsonResponse
    {
        $this->authorize('update', $contentPage);

        $validated = $request->validate([
            'source_section_id' => 'required|string|exists:content_sections,id',
        ]);

        $source = ContentSection::with('page')->findOrFail($validated['source_section_id']);
        $this->authorize('view', $source->page); // Quelle muss lesbar sein

        $new = $duplicator->pasteSection($source, $contentPage);

        return (new ContentSectionResource($new->load('sectionType', 'childSections.sectionType')))
            ->response()
            ->setStatusCode(201);
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

        $newParentId = $validated['parent_section_id'] ?? null;
        if ($newParentId !== null) {
            $parent = ContentSection::find($newParentId);

            // Neues Elter muss zur selben Seite gehören (kein Cross-Page-Reparenting).
            if (!$parent || $parent->content_page_id !== $contentSection->content_page_id) {
                throw ValidationException::withMessages(['parent_section_id' => 'Ungültiges Elternelement.']);
            }

            // Zyklus verhindern: Elternkette darf die Sektion selbst nicht enthalten.
            for ($cursor = $parent; $cursor !== null; $cursor = $cursor->parent_section_id ? ContentSection::find($cursor->parent_section_id) : null) {
                if ($cursor->id === $contentSection->id) {
                    throw ValidationException::withMessages(['parent_section_id' => 'Eine Sektion kann nicht unter sich selbst verschoben werden.']);
                }
            }
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
