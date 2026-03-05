<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreImportRequest;
use App\Http\Resources\Api\V1\ImportJobResource;
use App\Models\ImportJob;
use App\Models\ImportJobError;
use App\Models\ImportLog;
use App\Services\Export\ImportFormatExporter;
use App\Services\Import\ImportService;
use App\Services\Import\TemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    /**
     * POST /imports — upload Excel file, start validation.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $this->authorize('create', ImportJob::class);

        $importJob = $this->importService->upload(
            $request->file('file'),
            $request->user()->id,
        );

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

        $preview = $this->importService->preview($import);

        return response()->json(['data' => $preview]);
    }

    /**
     * POST /imports/{import}/execute — run the import.
     */
    public function execute(Request $request, ImportJob $import): JsonResponse
    {
        $this->authorize('update', $import);

        if ($import->status !== 'validated') {
            return $this->errorResponse(
                'urn:publixx:pim:import:invalid-state',
                'Import cannot be executed',
                422,
                "Import status is '{$import->status}', must be 'validated'."
            );
        }

        $force = (bool) $request->input('force', false);
        $mode = $request->input('mode', 'update'); // 'update', 'delete_insert' oder 'delete'
        if (!in_array($mode, ['update', 'delete_insert', 'delete'])) {
            $mode = 'update';
        }
        $this->importService->execute($import, $force, $mode);

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->fresh()->status,
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

    /**
     * GET /imports/templates/{type} — download blank import template.
     */
    public function template(string $type, TemplateGenerator $generator): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filePath = $generator->generate($type);

        return response()->download($filePath, "pim-import-template-{$type}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * GET /imports/{import}/logs — detailed execution log.
     */
    public function logs(Request $request, ImportJob $import): JsonResponse
    {
        $this->authorize('view', $import);

        $level = $request->input('level');
        $phase = $request->input('phase');

        $query = ImportLog::where('import_job_id', $import->id)
            ->orderBy('created_at');

        if ($level) {
            $query->where('level', $level);
        }
        if ($phase) {
            $query->where('phase', $phase);
        }

        $logs = $query->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * GET /imports/{import}/errors/download — download error report as Excel.
     */
    public function downloadErrors(ImportJob $import): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $import);

        $errors = ImportJobError::where('import_job_id', $import->id)->get();

        if ($errors->isEmpty()) {
            return response()->json(['message' => 'Keine Fehler vorhanden.'], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fehlerbericht');

        $headers = ['Zeile', 'Spalte', 'Feld', 'Wert', 'Fehler', 'Vorschlag', 'Sheet'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($errors as $error) {
            $sheet->setCellValue([1, $row], $error->row);
            $sheet->setCellValue([2, $row], $error->column);
            $sheet->setCellValue([3, $row], $error->field);
            $sheet->setCellValue([4, $row], $error->value);
            $sheet->setCellValue([5, $row], $error->error);
            $sheet->setCellValue([6, $row], $error->suggestion);
            $sheet->setCellValue([7, $row], $error->sheet);
            $row++;
        }

        $filePath = tempnam(sys_get_temp_dir(), 'import-errors-') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath, "fehlerbericht-{$import->id}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * GET /imports/export-format — export all data as re-importable Excel.
     */
    public function exportImportFormat(ImportFormatExporter $exporter): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $filePath = tempnam(sys_get_temp_dir(), 'pim-export-') . '.xlsx';

        try {
            $exporter->generate($filePath);
        } catch (\Throwable $e) {
            \Log::channel('import')->error('Export fehlgeschlagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Export fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }

        $timestamp = now()->format('Y-m-d_His');

        return response()->download($filePath, "pim-export-{$timestamp}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
