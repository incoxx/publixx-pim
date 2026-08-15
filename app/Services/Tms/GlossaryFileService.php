<?php

declare(strict_types=1);

namespace App\Services\Tms;

use App\Models\Language;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Glossar-Austausch: Begriffe als Datei heraus, übersetzt wieder herein.
 *
 * Zielgruppe sind Menschen, keine CAT-Werkzeuge — Attributnamen und
 * Wertelisten übersetzt üblicherweise der Landesvertrieb oder ein
 * Produktmanager, nicht eine Agentur. Deshalb XLSX/CSV mit allen Sprachen
 * nebeneinander statt XLIFF mit einem Sprachpaar je Datei: bei Terminologie
 * ist der Quervergleich über die Sprachen der eigentliche Wert.
 *
 * Rückweg-Schlüssel ist der TM-Hash. Fällt er aus (Spalte gelöscht, Datei
 * durch ein anderes Werkzeug gelaufen), greift der Quelltext als Ersatz.
 */
class GlossaryFileService
{
    /** Spalten vor den Sprachspalten. */
    private const array FIXED_HEADERS = ['hash', 'Bereich', 'Quelltext', 'Verwendet in'];

    public function __construct(private readonly TmsClient $client)
    {
    }

    // ─────────────────────────────────────────────────────────── Export ───

    /**
     * @param  string[]  $langs   Zielsprachen (leer = alle aktiven)
     * @param  string    $status  all | missing | auto | reviewed
     */
    public function export(array $langs, string $status = 'all', ?string $domain = null, string $format = 'xlsx'): StreamedResponse
    {
        $langs = !empty($langs) ? $langs : Language::targetCodes();

        if (empty($langs)) {
            abort(422, 'Keine Zielsprache gepflegt.');
        }

        $rows = $this->collectRows($langs, $status, $domain);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Glossar');

        // --- Kopfzeile ---
        $headers = self::FIXED_HEADERS;
        foreach ($langs as $lang) {
            $headers[] = $lang;
            $headers[] = "{$lang}_status";
        }

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $sheet->getStyle([1, 1, count($headers), 1])->getFont()->setBold(true);
        $sheet->getStyle([1, 1, count($headers), 1])->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
        $sheet->freezePane('A2');

        // --- Daten ---
        $rowNo = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit([1, $rowNo], $row['hash'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([2, $rowNo], $row['domain'] ?? '');
            $sheet->setCellValue([3, $rowNo], $row['source_text'] ?? '');
            $sheet->setCellValue([4, $rowNo], implode(' | ', $row['usages'] ?? []));

            $col = 5;
            foreach ($langs as $lang) {
                $t = $row['translations'][$lang] ?? null;
                $sheet->setCellValue([$col++, $rowNo], $t['text'] ?? '');
                $sheet->setCellValue([$col++, $rowNo], $t['status'] ?? '');
            }

            $rowNo++;
        }

        // --- Lesbarkeit ---
        $sheet->getColumnDimension('A')->setVisible(false);   // hash: nur Technik
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(45);
        $sheet->getColumnDimension('D')->setWidth(40);

        for ($c = 5; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(($c % 2 === 1) ? 35 : 12);
        }

        $sheet->getStyle([1, 2, count($headers), max(2, $rowNo - 1)])
            ->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        if ($rowNo > 2) {
            $sheet->setAutoFilter([1, 1, count($headers), $rowNo - 1]);
        }

        $name = 'glossar-' . implode('-', $langs) . '-' . date('Y-m-d');

        return $format === 'csv'
            ? $this->streamCsv($spreadsheet, $name)
            : $this->streamXlsx($spreadsheet, $name);
    }

    /**
     * Holt alle Seiten aus dem TMS.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectRows(array $langs, string $status, ?string $domain): array
    {
        $rows = [];
        $page = 1;

        do {
            $result = $this->client->glossaryPage($langs, array_filter([
                'status' => $status,
                'domain' => $domain,
                'page' => $page,
                'per_page' => 1000,
            ], fn ($v) => $v !== null && $v !== ''));

            if (empty($result['data'])) {
                break;
            }

            foreach ($result['data'] as $row) {
                $rows[] = $row;
            }

            $lastPage = (int) ($result['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage && $page <= 500); // Deckel gegen Endlosschleife

        return $rows;
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $name): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$name}.xlsx\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    private function streamCsv(Spreadsheet $spreadsheet, string $name): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            // Ausgeblendete Spalten exportiert der CSV-Writer mit — der Hash
            // muss in der CSV sichtbar sein, sonst ist der Rueckweg weg.
            $spreadsheet->getActiveSheet()->getColumnDimension('A')->setVisible(true);

            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');          // Excel-DE oeffnet ; ohne Import-Dialog
            $writer->setUseBOM(true);            // sonst zerlegt Excel die Umlaute
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    // ─────────────────────────────────────────────────────────── Import ───

    /**
     * Liest eine zuvor exportierte Datei und spielt die Übersetzungen zurück.
     *
     * @return array{updated: int, unchanged: int, unknown: int, skipped_rows: int, languages: string[]}
     */
    public function import(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $grid = $sheet->toArray(null, true, false, false);

        if (count($grid) < 2) {
            abort(422, 'Die Datei enthält keine Datenzeilen.');
        }

        $header = array_map(fn ($h) => trim((string) $h), array_shift($grid));

        $hashCol = $this->findColumn($header, 'hash');
        $sourceCol = $this->findColumn($header, 'quelltext');

        if ($hashCol === null && $sourceCol === null) {
            abort(422, 'Weder eine "hash"- noch eine "Quelltext"-Spalte gefunden. Bitte die exportierte Datei verwenden.');
        }

        // Sprachspalten sind alle Kopfzeilen, die wie ein Sprachcode aussehen
        // und keine Status-Spalte sind.
        $langCols = [];
        foreach ($header as $idx => $name) {
            if (preg_match('/^[a-z]{2}$/', $name)) {
                $langCols[$idx] = $name;
            }
        }

        if (empty($langCols)) {
            abort(422, 'Keine Sprachspalte gefunden. Erwartet werden Spalten wie "fi" oder "en".');
        }

        $items = [];
        $skipped = 0;

        foreach ($grid as $row) {
            $hash = $hashCol !== null ? trim((string) ($row[$hashCol] ?? '')) : '';

            // Rueckfall: fehlt der Hash, ueber den Quelltext neu berechnen.
            // Das rettet Dateien, die durch ein anderes Werkzeug gelaufen sind.
            if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
                $source = $sourceCol !== null ? trim((string) ($row[$sourceCol] ?? '')) : '';

                if ($source === '') {
                    $skipped++;
                    continue;
                }

                $hash = TmsHash::for(Language::sourceCode(), $source);
            }

            foreach ($langCols as $idx => $lang) {
                $value = trim((string) ($row[$idx] ?? ''));

                if ($value === '') {
                    continue;
                }

                $items[] = ['hash' => $hash, 'lang' => $lang, 'translation' => $value];
            }
        }

        if (empty($items)) {
            return [
                'updated' => 0, 'unchanged' => 0, 'unknown' => 0,
                'skipped_rows' => $skipped, 'languages' => array_values($langCols),
            ];
        }

        $result = $this->client->glossaryImport($items);

        return [
            'updated' => (int) ($result['updated'] ?? 0),
            'unchanged' => (int) ($result['unchanged'] ?? 0),
            'unknown' => (int) ($result['unknown'] ?? 0),
            'skipped_rows' => $skipped,
            'languages' => array_values($langCols),
        ];
    }

    private function findColumn(array $header, string $needle): ?int
    {
        foreach ($header as $idx => $name) {
            if (mb_strtolower($name) === $needle) {
                return $idx;
            }
        }

        return null;
    }
}
