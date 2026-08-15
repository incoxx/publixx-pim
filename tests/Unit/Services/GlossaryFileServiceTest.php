<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tms\GlossaryFileService;
use App\Services\Tms\TmsClient;
use App\Services\Tms\TmsHash;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Datei-Austausch des Glossars: XLSX/CSV erzeugen und wieder einlesen.
 *
 * Der Rundlauf wird echt geprüft — die erzeugte Datei wird zurückgelesen,
 * nicht bloß die Absicht getestet.
 */
class GlossaryFileServiceTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    private function service(array $glossaryRows = []): GlossaryFileService
    {
        config([
            'tms.enabled' => true,
            'tms.base_url' => 'http://tms.test/api',
            'tms.api_key' => 'test-key',
            'tms.target_languages' => ['fi', 'en'],
        ]);

        // Methodenbewusst: ein einfaches '*/glossary*'-Muster wuerde auch den
        // POST des Imports abfangen und ihm die GET-Antwort unterschieben.
        Http::fake(function ($request) use ($glossaryRows) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'updated' => count($request['items'] ?? []),
                    'unchanged' => 0,
                    'unknown' => 0,
                ]);
            }

            return Http::response([
                'data' => $glossaryRows,
                'current_page' => 1,
                'last_page' => 1,
                'total' => count($glossaryRows),
            ]);
        });

        return new GlossaryFileService(new TmsClient());
    }

    private function row(string $text, array $translations = [], array $usages = []): array
    {
        return [
            'hash' => TmsHash::for('de', $text),
            'domain' => 'attribute',
            'source_lang' => 'de',
            'source_text' => $text,
            'usages' => $usages,
            'translations' => $translations,
        ];
    }

    /** Antwort der StreamedResponse in eine Datei schreiben. */
    private function capture(\Symfony\Component\HttpFoundation\StreamedResponse $response, string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'glossar_') . '.' . $ext;
        $this->tmpFiles[] = $path;

        ob_start();
        $response->sendContent();
        file_put_contents($path, ob_get_clean());

        return $path;
    }

    // ─── Export ──────────────────────────────────────────────────────────

    public function test_export_produces_a_readable_workbook(): void
    {
        $service = $this->service([
            $this->row('Gewicht', ['fi' => ['text' => 'Paino', 'status' => 'auto']], ['attribute: gewicht']),
            $this->row('Breite'),
        ]);

        $path = $this->capture($service->export(['fi'], 'all'), 'xlsx');
        $grid = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);

        // Kopfzeile: feste Spalten + je Sprache Wert und Status
        $this->assertSame(['hash', 'Bereich', 'Quelltext', 'Verwendet in', 'fi', 'fi_status'], $grid[0]);

        $this->assertSame(TmsHash::for('de', 'Gewicht'), $grid[1][0]);
        $this->assertSame('attribute', $grid[1][1]);
        $this->assertSame('Gewicht', $grid[1][2]);
        $this->assertSame('attribute: gewicht', $grid[1][3]);
        $this->assertSame('Paino', $grid[1][4]);
        $this->assertSame('auto', $grid[1][5]);

        // Zweiter Begriff ohne Übersetzung — leere Zelle zum Ausfüllen
        $this->assertSame('Breite', $grid[2][2]);
        $this->assertSame('', (string) $grid[2][4]);
    }

    public function test_export_writes_one_column_pair_per_language(): void
    {
        $service = $this->service([$this->row('Gewicht')]);

        $path = $this->capture($service->export(['fi', 'en', 'fr'], 'all'), 'xlsx');
        $header = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false)[0];

        $this->assertSame(
            ['hash', 'Bereich', 'Quelltext', 'Verwendet in', 'fi', 'fi_status', 'en', 'en_status', 'fr', 'fr_status'],
            $header,
        );
    }

    public function test_export_falls_back_to_configured_languages(): void
    {
        $service = $this->service([$this->row('Gewicht')]);

        $path = $this->capture($service->export([], 'all'), 'xlsx');
        $header = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false)[0];

        $this->assertContains('fi', $header);
        $this->assertContains('en', $header);
    }

    public function test_csv_export_keeps_the_hash_column_visible(): void
    {
        // In der XLSX ist der Hash ausgeblendet; in der CSV muss er sichtbar
        // bleiben, sonst ist der Rueckweg verloren.
        $service = $this->service([$this->row('Gewicht')]);

        $path = $this->capture($service->export(['fi'], 'all', null, 'csv'), 'csv');
        $csv = file_get_contents($path);

        $this->assertStringContainsString(TmsHash::for('de', 'Gewicht'), $csv);
        $this->assertStringContainsString('Gewicht', $csv);
    }

    public function test_export_requests_the_status_filter_from_the_tms(): void
    {
        $service = $this->service([$this->row('Breite')]);

        $this->capture($service->export(['fi'], 'missing'), 'xlsx');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/glossary')
            && $request['status'] === 'missing'
            && $request['langs'] === 'fi');
    }

    // ─── Rundlauf ────────────────────────────────────────────────────────

    public function test_roundtrip_export_fill_in_import(): void
    {
        $service = $this->service([$this->row('Gewicht'), $this->row('Breite')]);

        $path = $this->capture($service->export(['fi'], 'missing'), 'xlsx');

        // Der Uebersetzer fuellt die fi-Spalte aus
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue([5, 2], 'Paino');
        $sheet->setCellValue([5, 3], 'Leveys');

        $filled = tempnam(sys_get_temp_dir(), 'glossar_') . '.xlsx';
        $this->tmpFiles[] = $filled;
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($filled);

        $result = $service->import($filled);

        $this->assertSame(2, $result['updated']);
        $this->assertSame(['fi'], $result['languages']);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            $items = collect($request['items'] ?? []);

            return $items->count() === 2
                && $items->firstWhere('hash', TmsHash::for('de', 'Gewicht'))['translation'] === 'Paino'
                && $items->firstWhere('hash', TmsHash::for('de', 'Breite'))['translation'] === 'Leveys'
                && $items->every(fn ($i) => $i['lang'] === 'fi');
        });
    }

    public function test_import_recovers_when_the_hash_column_was_destroyed(): void
    {
        // Dateien laufen durch fremde Werkzeuge; der Quelltext rettet den Rueckweg.
        $service = $this->service([$this->row('Gewicht')]);
        $path = $this->capture($service->export(['fi'], 'all'), 'xlsx');

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue([1, 2], '');          // Hash geloescht
        $sheet->setCellValue([5, 2], 'Paino');

        $broken = tempnam(sys_get_temp_dir(), 'glossar_') . '.xlsx';
        $this->tmpFiles[] = $broken;
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($broken);

        $result = $service->import($broken);

        $this->assertSame(1, $result['updated']);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            // Hash aus dem Quelltext neu berechnet
            return ($request['items'][0]['hash'] ?? null) === TmsHash::for('de', 'Gewicht');
        });
    }

    public function test_import_skips_empty_cells(): void
    {
        $service = $this->service([$this->row('Gewicht'), $this->row('Breite')]);
        $path = $this->capture($service->export(['fi'], 'all'), 'xlsx');

        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getActiveSheet()->setCellValue([5, 2], 'Paino'); // nur die erste Zeile

        $partial = tempnam(sys_get_temp_dir(), 'glossar_') . '.xlsx';
        $this->tmpFiles[] = $partial;
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($partial);

        $service->import($partial);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            return count($request['items']) === 1;
        });
    }

    public function test_import_rejects_a_file_without_language_columns(): void
    {
        $service = $this->service();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['hash', 'Bereich', 'Quelltext'], ['x', 'y', 'z']]);

        $path = tempnam(sys_get_temp_dir(), 'glossar_') . '.xlsx';
        $this->tmpFiles[] = $path;
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->import($path);
    }
}
