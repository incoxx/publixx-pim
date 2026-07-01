<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\VirtualProductDefinitionResource;
use App\Http\Resources\Api\V1\VirtualProductInheritanceRuleResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\Product;
use App\Models\VirtualProductDefinition;
use App\Models\VirtualProductInheritanceRule;
use App\Models\WatchlistItem;
use App\Services\VirtualProduct\VirtualProductAttributeSyncService;
use App\Services\VirtualProduct\VirtualProductResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Virtuelle Produkte (dynamische Cluster).
 *
 * Verwaltet die Definition eines virtuellen Produkts und löst dessen
 * Mitglieder live über den VirtualProductResolver auf.
 */
class VirtualProductController extends Controller
{
    use AuditsChanges;
    use ChecksTabPermissions;

    public function __construct(
        private readonly VirtualProductResolver $resolver,
        private readonly VirtualProductAttributeSyncService $syncService,
    ) {}

    /**
     * GET /products/{product}/virtual-members
     *
     * Löst die Mitglieder des virtuellen Produkts live auf (paginiert).
     */
    public function members(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $definition = $product->virtualDefinition;

        if ($definition === null) {
            // Kein virtuelles Produkt → leere Mitgliederliste
            return ProductResource::collection(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, (int) $request->input('per_page', 50))
            );
        }

        $members = $this->resolver->paginate(
            $definition,
            (int) $request->input('per_page', 50),
            (int) $request->input('page', 1),
            ['productType', 'media'],
        );

        return ProductResource::collection($members);
    }

    /**
     * GET /products/{product}/virtual-definition
     */
    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $definition = $product->virtualDefinition;

        if ($definition === null) {
            return response()->json(['data' => null]);
        }

        return (new VirtualProductDefinitionResource(
            $definition->load(['searchProfile', 'relationType'])
        ))->response();
    }

    /**
     * PUT /products/{product}/virtual-definition
     *
     * Legt die Cluster-Definition an oder aktualisiert sie (Upsert).
     */
    public function upsert(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('relations');

        if (! $product->isVirtual()) {
            throw ValidationException::withMessages([
                'product_type_ref' => 'Eine Cluster-Definition kann nur für virtuelle Produkte (product_type_ref=virtual) angelegt werden.',
            ]);
        }

        $data = $request->validate([
            'source_type' => ['required', Rule::in([
                VirtualProductDefinition::SOURCE_SEARCH_PROFILE,
                VirtualProductDefinition::SOURCE_PQL,
                VirtualProductDefinition::SOURCE_MANUAL,
            ])],
            'search_profile_id' => ['nullable', 'required_if:source_type,search_profile', 'string', 'exists:search_profiles,id'],
            'pql_query' => ['nullable', 'required_if:source_type,pql', 'string', 'max:5000'],
            // Bewusst kein required_if: eine leere manuelle Auswahl ist ein
            // gültiger Zustand (z.B. letztes Mitglied entfernt).
            'manual_product_ids' => ['nullable', 'array'],
            'manual_product_ids.*' => ['string', 'exists:products,id'],
            'relation_type_id' => ['nullable', 'string', 'exists:product_relation_types,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'max_members' => ['nullable', 'integer', 'min:1'],
        ]);

        // Felder, die nicht zur gewählten Quelle gehören, leeren
        $data = $this->normalizeForSource($data);

        $definition = VirtualProductDefinition::updateOrCreate(
            ['product_id' => $product->id],
            $data,
        );

        $this->audit('virtual_definition_saved', 'Product', $product->id, null, [
            'source_type' => $definition->source_type,
        ]);

        return (new VirtualProductDefinitionResource(
            $definition->load(['searchProfile', 'relationType'])
        ))->response()->setStatusCode(200);
    }

    /**
     * DELETE /products/{product}/virtual-definition
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('relations');

        $definition = $product->virtualDefinition;

        if ($definition !== null) {
            $definition->delete();
            $this->audit('virtual_definition_removed', 'Product', $product->id);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /products/{product}/virtual-definition/from-watchlist
     *
     * Übernimmt die aktuelle Merkliste des Nutzers als manuelle Cluster-Mitglieder.
     */
    public function fromWatchlist(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('relations');

        if (! $product->isVirtual()) {
            throw ValidationException::withMessages([
                'product_type_ref' => 'Eine Cluster-Definition kann nur für virtuelle Produkte (product_type_ref=virtual) angelegt werden.',
            ]);
        }

        $productIds = WatchlistItem::where('user_id', $request->user()->id)
            ->where('product_id', '!=', $product->id)
            ->orderBy('created_at', 'desc')
            ->pluck('product_id')
            ->all();

        $definition = VirtualProductDefinition::updateOrCreate(
            ['product_id' => $product->id],
            [
                'source_type' => VirtualProductDefinition::SOURCE_MANUAL,
                'search_profile_id' => null,
                'pql_query' => null,
                'manual_product_ids' => $productIds,
            ],
        );

        $this->audit('virtual_definition_saved', 'Product', $product->id, null, [
            'source_type' => $definition->source_type,
            'member_count' => count($productIds),
        ]);

        return (new VirtualProductDefinitionResource(
            $definition->load(['searchProfile', 'relationType'])
        ))->response()->setStatusCode(200);
    }

    /**
     * GET /products/{product}/virtual-inheritance-rules
     *
     * Liefert die Vererbungsregeln des virtuellen Produkts (welche Attribute
     * per Sync an die Mitglieder vererbt werden, inkl. Konfliktverhalten).
     */
    public function inheritanceRules(Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $rules = $product->inheritanceRules()->with('attribute')->get();

        return VirtualProductInheritanceRuleResource::collection($rules);
    }

    /**
     * PUT /products/{product}/virtual-inheritance-rules
     *
     * Ersetzt den kompletten Regelsatz (Upsert + Löschen der nicht mehr
     * enthaltenen Attribute). Die Werte selbst werden weiterhin ganz normal
     * im Attribut-Tab des virtuellen Produkts gepflegt.
     */
    public function saveInheritanceRules(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('relations');

        if (! $product->isVirtual()) {
            throw ValidationException::withMessages([
                'product_type_ref' => 'Vererbungsregeln können nur für virtuelle Produkte (product_type_ref=virtual) angelegt werden.',
            ]);
        }

        $data = $request->validate([
            'rules' => ['present', 'array'],
            'rules.*.attribute_id' => ['required', 'string', 'exists:attributes,id'],
            'rules.*.conflict_mode' => ['required', Rule::in([
                VirtualProductInheritanceRule::CONFLICT_KEEP_LOCAL,
                VirtualProductInheritanceRule::CONFLICT_FORCE_OVERRIDE,
            ])],
        ]);

        $attributeIds = collect($data['rules'])->pluck('attribute_id')->all();

        $product->inheritanceRules()->whereNotIn('attribute_id', $attributeIds)->delete();

        foreach ($data['rules'] as $rule) {
            VirtualProductInheritanceRule::updateOrCreate(
                ['virtual_product_id' => $product->id, 'attribute_id' => $rule['attribute_id']],
                ['conflict_mode' => $rule['conflict_mode']],
            );
        }

        $this->audit('virtual_inheritance_rules_saved', 'Product', $product->id, null, [
            'attribute_count' => count($attributeIds),
        ]);

        return VirtualProductInheritanceRuleResource::collection(
            $product->inheritanceRules()->with('attribute')->get()
        );
    }

    /**
     * POST /products/{product}/virtual-definition/sync
     *
     * Vererbt die per Regel definierten Attributwerte an die aktuellen
     * Mitglieder. Deklarativ: entfernt auch Werte, deren Regel gelöscht
     * wurde oder deren Mitglied den Cluster verlassen hat.
     */
    public function sync(Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('relations');

        if (! $product->isVirtual()) {
            throw ValidationException::withMessages([
                'product_type_ref' => 'Nur virtuelle Produkte (product_type_ref=virtual) können synchronisiert werden.',
            ]);
        }

        $report = $this->syncService->sync($product);

        $this->audit('virtual_attributes_synced', 'Product', $product->id, null, $report);

        return response()->json(['data' => $report]);
    }

    /**
     * Leert die Quell-Felder, die nicht zum gewählten source_type gehören,
     * damit keine widersprüchlichen Definitionen entstehen.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeForSource(array $data): array
    {
        return match ($data['source_type']) {
            VirtualProductDefinition::SOURCE_SEARCH_PROFILE => array_merge($data, [
                'pql_query' => null,
                'manual_product_ids' => null,
            ]),
            VirtualProductDefinition::SOURCE_PQL => array_merge($data, [
                'search_profile_id' => null,
                'manual_product_ids' => null,
            ]),
            VirtualProductDefinition::SOURCE_MANUAL => array_merge($data, [
                'search_profile_id' => null,
                'pql_query' => null,
            ]),
            default => $data,
        };
    }
}
