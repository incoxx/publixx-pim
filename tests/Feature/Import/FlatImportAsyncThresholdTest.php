<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Jobs\ExecuteFlatImportJob;
use App\Models\ImportJob;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FlatImportAsyncThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function storeFlatImportFile(int $rows): ImportJob
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', 'Name');

        for ($i = 1; $i <= $rows; $i++) {
            $sheet->setCellValue('A' . ($i + 1), 'SKU-' . $i);
            $sheet->setCellValue('B' . ($i + 1), 'Product ' . $i);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'flat_import') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $importJob = ImportJob::factory()->create([
            'status' => 'validated',
            'file_path' => 'imports/' . uniqid('flat_import_test_', true) . '.xlsx',
        ]);

        Storage::disk('local')->put($importJob->file_path, file_get_contents($tmpPath));
        unlink($tmpPath);

        return $importJob;
    }

    public function test_small_flat_import_runs_synchronously(): void
    {
        Queue::fake();
        $importJob = $this->storeFlatImportFile(5);

        app(ImportService::class)->executeFlatImport($importJob, [
            'mode' => 'update',
            'sku_column' => 'SKU',
            'name_column' => 'Name',
            'column_mappings' => [],
        ]);

        Queue::assertNothingPushed();
        $this->assertSame('completed', $importJob->fresh()->status);
    }

    public function test_large_flat_import_dispatches_queue_job_instead_of_blocking(): void
    {
        Queue::fake();
        $importJob = $this->storeFlatImportFile(150);

        app(ImportService::class)->executeFlatImport($importJob, [
            'mode' => 'update',
            'sku_column' => 'SKU',
            'name_column' => 'Name',
            'column_mappings' => [],
        ]);

        Queue::assertPushed(ExecuteFlatImportJob::class);
        // Muss sofort zurückkehren, ohne die Zeilen synchron zu verarbeiten.
        $this->assertSame('executing', $importJob->fresh()->status);
    }
}
