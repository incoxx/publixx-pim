<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ImportCollectionRequest;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Collection;
use App\Models\CollectionType;
use App\Services\Collections\CollectionFactory;
use App\Services\Collections\Inbound\CsvAdapter;
use App\Services\Collections\Inbound\GenericJsonAdapter;
use App\Services\Collections\Inbound\InboundAdapterInterface;
use App\Services\Collections\Inbound\OpenTransRfqAdapter;
use Illuminate\Http\JsonResponse;

class CollectionImportController extends Controller
{
    /**
     * POST /collections/import
     *
     * Body: multipart mit `file` ODER `payload` (Rohtext), `adapter` (json|csv|opentrans),
     * `collection_type_id`, optional `organization`/`currency` (v.a. fuer CSV, das selbst
     * keine Empfaenger-Struktur mitbringt).
     */
    public function import(ImportCollectionRequest $request, CollectionFactory $factory): JsonResponse
    {
        $this->authorize('create', Collection::class);
        abort_unless(config('collections.import_enabled'), 403, 'Collections-Import ist deaktiviert.');

        $type = CollectionType::findOrFail($request->validated('collection_type_id'));
        $adapter = $this->resolveAdapter($request->validated('adapter'));

        $raw = $request->hasFile('file')
            ? file_get_contents($request->file('file')->getRealPath())
            : (string) $request->validated('payload');

        try {
            $context = $adapter->parse($raw, [
                'organization' => $request->validated('organization'),
                'currency' => $request->validated('currency'),
            ]);
        } catch (\Throwable $e) {
            abort(422, 'Import fehlgeschlagen: ' . $e->getMessage());
        }

        abort_if(empty($context->items), 422, 'Der Import enthaelt keine Positionen.');

        $collection = $factory->fromOfferContext($context, $type);

        return (new CollectionResource($collection))
            ->response()
            ->setStatusCode(201);
    }

    private function resolveAdapter(string $key): InboundAdapterInterface
    {
        return match ($key) {
            'json' => new GenericJsonAdapter(),
            'csv' => new CsvAdapter(),
            'opentrans' => new OpenTransRfqAdapter(),
        };
    }
}
