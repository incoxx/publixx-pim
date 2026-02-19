<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreImportRequest;
use App\Http\Resources\Api\V1\ImportJobResource;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ImportController — defines the API endpoints for import.
 *
 * The actual import logic (validation, preview, execution) is implemented by
 * Agent 6 (Import-Agent) in App\Services\Import\ImportService.
 * This controller delegates to that service.
 */
class ImportController extends Controller
{
    /**
     * POST /imports — upload Excel file, start validation.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $this->authorize('create', ImportJob::class);

        $file = $request->file('file');
        $path = $file->store('imports', 'local');

        $importJob = ImportJob::create([
            'user_id' => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        // Agent 6 provides: App\Services\Import\ImportService
        // dispatch(new \App\Jobs\ImportValidate($importJob));

        return (new ImportJobResource($importJob))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /imports/{import} — status + validation result.
     */
    public function show(ImportJob $import): ImportJobResource
    {
        $this->authorize('view', $import);

        return new ImportJobResource($import);
    }

    /**
     * GET /imports/{import}/preview — preview (create/update/skip counts).
     */
    public function preview(ImportJob $import): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status,
                'summary' => $import->summary,
                'sheets_found' => $import->sheets_found,
            ],
        ]);
    }

    /**
     * POST /imports/{import}/execute — run the import.
     */
    public function execute(ImportJob $import): JsonResponse
    {
        $this->authorize('update', $import);

        if (!in_array($import->status, ['validated'])) {
            return $this->errorResponse(
                'urn:publixx:pim:import:invalid-state',
                'Import cannot be executed',
                422,
                "Import status is '{$import->status}', must be 'validated'."
            );
        }

        // Agent 6 provides: dispatch(new \App\Jobs\ImportExecute($import));
        $import->update(['status' => 'executing', 'started_at' => now()]);

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => 'executing',
            ],
        ]);
    }

    /**
     * GET /imports/{import}/result — result report.
     */
    public function result(ImportJob $import): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status,
                'result' => $import->result,
                'started_at' => $import->started_at,
                'completed_at' => $import->completed_at,
            ],
        ]);
    }

    /**
     * DELETE /imports/{import} — cancel / delete import.
     */
    public function destroy(ImportJob $import): JsonResponse
    {
        $this->authorize('delete', $import);

        $import->delete();

        return response()->json(null, 204);
    }
}
