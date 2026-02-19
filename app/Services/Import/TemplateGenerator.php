<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Erzeugt ein leeres Excel-Import-Template mit 14 Reitern
 * und korrekten Headern gemäß Spezifikation.
 */
class TemplateGenerator
{
    /**
     * Header-Definitionen pro Sheet.
     * Pflichtfelder sind mit * markiert.
     */
    private const array SHEET_HEADERS = [
        '01_Produkttypen' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Hat Varianten (Ja/Nein)',
            'F' => 'Hat EAN (Ja/Nein)',
            'G' => 'Hat Preise (Ja/Nein)',
            'H' => 'Hat Medien (Ja/Nein)',
        ],
        '02_Attributgruppen' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Sortierung',
        ],
        '03_Einheiten' => [
            'A' => 'Gruppe Techn. Name*',
            'B' => 'Gruppe Name (Deutsch)*',
            'C' => 'Einheit Techn. Name*',
            'D' => 'Kürzel*',
            'E' => 'Umrechnungsfaktor',
            'F' => 'Basiseinheit (Ja/Nein)',
        ],
        '04_Wertelisten' => [
            'A' => 'Liste Techn. Name*',
            'B' => 'Liste Name (Deutsch)*',
            'C' => 'Eintrag Techn. Name',
            'D' => 'Anzeigename (Deutsch)',
            'E' => 'Anzeigename (Englisch)',
            'F' => 'Sortierung',
        ],
        '05_Attribute' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Datentyp*',
            'F' => 'Attributgruppe',
            'G' => 'Werteliste',
            'H' => 'Einheitengruppe',
            'I' => 'Standard-Einheit',
            'J' => 'Vermehrbar (Ja/Nein)',
            'K' => 'Max. Vermehrungen',
            'L' => 'Übersetzbar (Ja/Nein)',
            'M' => 'Pflicht (Optional/Pflicht)',
            'N' => 'Eindeutig (Ja/Nein)',
            'O' => 'Suchbar (Ja/Nein)',
            'P' => 'Vererbbar (Ja/Nein)',
            'Q' => 'Übergeordnetes Attribut',
            'R' => 'Quellsystem',
            'S' => 'Sichten (kommasepariert)',
        ],
        '06_Hierarchien' => [
            'A' => 'Hierarchie*',
            'B' => 'Typ* (master/output)',
            'C' => 'Ebene 1',
            'D' => 'Ebene 2',
            'E' => 'Ebene 3',
            'F' => 'Ebene 4',
            'G' => 'Ebene 5',
            'H' => 'Ebene 6',
        ],
        '07_Hierarchie_Attribute' => [
            'A' => 'Hierarchie*',
            'B' => 'Knotenpfad*',
            'C' => 'Attribut*',
            'D' => 'Sammlungsname',
            'E' => 'Sammlungs-Sortierung',
            'F' => 'Attribut-Sortierung',
            'G' => 'Nicht vererben (Ja/Nein)',
        ],
        '08_Produkte' => [
            'A' => 'SKU*',
            'B' => 'Produktname*',
            'C' => 'Produktname (EN)',
            'D' => 'Produkttyp*',
            'E' => 'EAN',
            'F' => 'Status (draft/active/inactive)',
        ],
        '09_Produktwerte' => [
            'A' => 'SKU*',
            'B' => 'Attribut*',
            'C' => 'Wert*',
            'D' => 'Einheit',
            'E' => 'Sprache (de/en/...)',
            'F' => 'Index',
        ],
        '10_Varianten' => [
            'A' => 'Eltern-SKU*',
            'B' => 'Varianten-SKU*',
            'C' => 'Variantenname*',
            'D' => 'Variantenname (EN)',
            'E' => 'EAN',
            'F' => 'Status',
        ],
        '11_Produkt_Hierarchien' => [
            'A' => 'SKU*',
            'B' => 'Hierarchie*',
            'C' => 'Knotenpfad*',
        ],
        '12_Produktbeziehungen' => [
            'A' => 'Quell-SKU*',
            'B' => 'Ziel-SKU*',
            'C' => 'Beziehungstyp*',
            'D' => 'Sortierung',
        ],
        '13_Preise' => [
            'A' => 'SKU*',
            'B' => 'Preisart*',
            'C' => 'Betrag*',
            'D' => 'Währung* (EUR/USD/...)',
            'E' => 'Gültig ab',
            'F' => 'Gültig bis',
            'G' => 'Land (ISO 2)',
            'H' => 'Staffel von',
            'I' => 'Staffel bis',
        ],
        '14_Medien' => [
            'A' => 'SKU*',
            'B' => 'Dateiname*',
            'C' => 'Medientyp (image/document/video)',
            'D' => 'Verwendung (teaser/gallery/document)',
            'E' => 'Titel (Deutsch)',
            'F' => 'Titel (Englisch)',
            'G' => 'Alt-Text (Deutsch)',
            'H' => 'Sortierung',
            'I' => 'Primär (Ja/Nein)',
        ],
    ];

    /**
     * Erlaubte Werte für Dropdown-Validierungen (Hinweise).
     */
    private const array ENUM_HINTS = [
        '05_Attribute' => [
            'E' => 'String, Number, Float, Date, Flag, Selection, Dictionary, Collection',
            'R' => 'PIM, SAP ERP, Other',
        ],
        '06_Hierarchien' => [
            'B' => 'master, output',
        ],
        '08_Produkte' => [
            'F' => 'draft, active, inactive, discontinued',
        ],
    ];

    /**
     * Erzeugt ein leeres Import-Template.
     *
     * @param string $outputPath Ziel-Dateipfad
     * @return string Pfad der erzeugten Datei
     */
    public function generate(string $outputPath): string
    {
        $spreadsheet = new Spreadsheet();

        // Default-Sheet entfernen
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;
        foreach (self::SHEET_HEADERS as $sheetName => $headers) {
            $worksheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($worksheet, $sheetIndex);
            $sheetIndex++;

            $this->writeHeaders($worksheet, $headers);
            $this->styleHeaders($worksheet, $headers);
            $this->addEnumHints($worksheet, $sheetName);
            $this->autoSizeColumns($worksheet, $headers);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }

    /**
     * Schreibt Header in Zeile 1.
     */
    private function writeHeaders(Worksheet $worksheet, array $headers): void
    {
        foreach ($headers as $column => $headerText) {
            $worksheet->setCellValue($column . '1', $headerText);
        }
    }

    /**
     * Formatiert die Header-Zeile.
     */
    private function styleHeaders(Worksheet $worksheet, array $headers): void
    {
        $lastColumn = array_key_last($headers);
        $range = 'A1:' . $lastColumn . '1';

        $worksheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2B5797'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF999999'],
                ],
            ],
        ]);

        $worksheet->getRowDimension(1)->setRowHeight(30);

        // Pflichtfelder (mit *) in Gelb markieren
        foreach ($headers as $column => $headerText) {
            if (str_contains($headerText, '*')) {
                $worksheet->getStyle($column . '1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD4A017');
            }
        }
    }

    /**
     * Fügt in Zeile 2 Enum-Hinweise als Kommentar/Beispiel ein.
     */
    private function addEnumHints(Worksheet $worksheet, string $sheetName): void
    {
        if (!isset(self::ENUM_HINTS[$sheetName])) {
            return;
        }

        foreach (self::ENUM_HINTS[$sheetName] as $column => $hint) {
            $worksheet->getComment($column . '1')
                ->getText()
                ->createTextRun("Erlaubte Werte:\n" . $hint)
                ->getFont()
                ->setSize(9);
        }
    }

    /**
     * Passt die Spaltenbreiten an.
     */
    private function autoSizeColumns(Worksheet $worksheet, array $headers): void
    {
        foreach (array_keys($headers) as $column) {
            $worksheet->getColumnDimension($column)->setWidth(22);
        }

        // Erste Spalte etwas breiter
        $worksheet->getColumnDimension('A')->setWidth(28);
    }
}
