<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tms\Jobs\ProcessIngestBatchJob;

class IngestController
{
    /**
     * POST /api/ingest
     *
     * Accepts a batch of entities and queues them for processing.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'entities' => 'required|array',
            'entities.*.entity_type' => 'required|string|max:50',
            'entities.*.entity_id' => 'required|string|max:36',
            'entities.*.entity_label' => 'nullable|string|max:255',
            'entities.*.fields' => 'required|array|min:1',
            'entities.*.fields.*.field' => 'required|string|max:50',
            'entities.*.fields.*.text' => 'required|string',
            'entities.*.fields.*.lang' => 'required|string|max:5',
        ]);

        $entities = $request->input('entities');

        ProcessIngestBatchJob::dispatch($entities);

        return response()->json([
            'message' => 'Ingest accepted.',
            'count' => count($entities),
        ], 202);
    }
}
