<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generiert einen vollständigen Test-Katalog als Excel-Datei.
 *
 * Nutzung:
 *   php artisan pim:test-catalog
 *   php artisan pim:test-catalog --output=/tmp/test-katalog.xlsx
 */
class GenerateTestCatalog extends Command
{
    protected $signature = 'pim:test-catalog
        {--output= : Ausgabepfad (Standard: storage/app/test-katalog-anypim.xlsx)}';

    protected $description = 'Generiert einen vollständigen Test-Katalog über alle PIM-Bereiche als Excel-Datei';

    public function handle(): int
    {
        $output = $this->option('output') ?: storage_path('app/test-katalog-anypim.xlsx');

        $this->info('Generiere Test-Katalog...');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Test-Katalog anyPIM');

        // Header
        $headers = ['TC-Nr', 'Bereich', 'Testfall', 'Vorhergehende Aktion', 'Erwartetes Ergebnis'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
        }

        // Header-Styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B579A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Testfälle einfügen
        $testCases = $this->getTestCases();
        $row = 2;
        $tcNr = 1;

        foreach ($testCases as $tc) {
            $sheet->setCellValue("A{$row}", 'TC-' . str_pad((string) $tcNr, 3, '0', STR_PAD_LEFT));
            $sheet->setCellValue("B{$row}", $tc[0]);
            $sheet->setCellValue("C{$row}", $tc[1]);
            $sheet->setCellValue("D{$row}", $tc[2]);
            $sheet->setCellValue("E{$row}", $tc[3]);
            $row++;
            $tcNr++;
        }

        // Daten-Styling
        $lastRow = $row - 1;
        $dataStyle = [
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
        ];
        $sheet->getStyle("A2:E{$lastRow}")->applyFromArray($dataStyle);

        // Zebra-Streifen
        for ($r = 2; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:E{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F6FC');
            }
        }

        // Bereichs-Trennzeilen hervorheben (erster TC eines neuen Bereichs)
        $lastBereich = '';
        for ($r = 2; $r <= $lastRow; $r++) {
            $bereich = $sheet->getCell("B{$r}")->getValue();
            if ($bereich !== $lastBereich) {
                $sheet->getStyle("A{$r}:E{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DCE6F1');
                $sheet->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
                $lastBereich = $bereich;
            }
        }

        // Spaltenbreiten
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(50);

        // Auto-Filter
        $sheet->setAutoFilter("A1:E{$lastRow}");

        // Freeze Header
        $sheet->freezePane('A2');

        // Schreiben
        $dir = dirname($output);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $this->info("Test-Katalog mit " . ($tcNr - 1) . " Testfällen erstellt: {$output}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function getTestCases(): array
    {
        return array_merge(
            $this->systemAuthentifizierung(),
            $this->einheitengruppen(),
            $this->wertelisten(),
            $this->attributsichten(),
            $this->attributtypen(),
            $this->attribute(),
            $this->produkttypen(),
            $this->hierarchien(),
            $this->produkte(),
            $this->preise(),
            $this->beziehungen(),
            $this->medien(),
            $this->varianten(),
            $this->versionierung(),
            $this->massendatenpflege(),
            $this->importFunktionen(),
            $this->exportFunktionen(),
            $this->suchePql(),
            $this->reports(),
            $this->merkliste(),
            $this->publixxIntegration(),
            $this->preview(),
            $this->benutzerverwaltung(),
            $this->uebersetzungen(),
            $this->woerterbuch(),
            $this->einstellungenAdmin(),
            $this->katalogApi(),
            $this->auditLog(),
        );
    }

    private function systemAuthentifizierung(): array
    {
        $b = 'System & Authentifizierung';
        return [
            [$b, 'Login mit gültigen Zugangsdaten', 'System ist erreichbar, Benutzer existiert', 'Erfolgreicher Login, JWT-Token wird zurückgegeben, Dashboard wird angezeigt'],
            [$b, 'Login mit ungültigen Zugangsdaten', 'System ist erreichbar', 'Fehlermeldung „Ungültige Zugangsdaten", kein Token wird ausgestellt'],
            [$b, 'Token-Refresh', 'Benutzer ist eingeloggt', 'Neues Token wird ausgestellt, altes Token ist ungültig'],
            [$b, 'Aktuelle Benutzerinfos abrufen (me)', 'Benutzer ist eingeloggt', 'Name, E-Mail, Rolle und Berechtigungen werden korrekt zurückgegeben'],
            [$b, 'Logout', 'Benutzer ist eingeloggt', 'Token wird invalidiert, geschützte Endpunkte sind nicht mehr erreichbar'],
            [$b, 'Health-Check Endpunkt', 'System ist gestartet', 'HTTP 200 mit Status „ok" wird zurückgegeben'],
        ];
    }

    private function einheitengruppen(): array
    {
        $b = 'Einheitengruppen & Einheiten';
        return [
            [$b, 'Einheitengruppe anlegen', 'Benutzer ist eingeloggt', 'Einheitengruppe wird mit Name und technischem Namen erstellt, in der Liste sichtbar'],
            [$b, 'Einheitengruppe bearbeiten', 'Einheitengruppe existiert', 'Name wird aktualisiert, Änderung in der Liste sichtbar'],
            [$b, 'Einheitengruppe löschen (ohne Einheiten)', 'Einheitengruppe existiert, keine Einheiten zugeordnet', 'Einheitengruppe wird gelöscht, nicht mehr in der Liste'],
            [$b, 'Einheitengruppe löschen (mit Einheiten)', 'Einheitengruppe hat zugeordnete Einheiten', 'Löschung wird verhindert oder Warnung angezeigt'],
            [$b, 'Einheit anlegen', 'Einheitengruppe existiert', 'Einheit wird mit Name, Symbol, technischem Namen und Umrechnungsfaktor erstellt'],
            [$b, 'Einheit bearbeiten', 'Einheit existiert', 'Symbol und Umrechnungsfaktor werden aktualisiert'],
            [$b, 'Einheit löschen (nicht in Verwendung)', 'Einheit existiert, kein Attribut nutzt sie', 'Einheit wird gelöscht'],
            [$b, 'Einheit löschen (in Verwendung)', 'Einheit ist einem Attribut zugewiesen', 'Löschung wird verhindert oder Warnung angezeigt'],
            [$b, 'Einheiten sortieren', 'Mehrere Einheiten in einer Gruppe', 'Reihenfolge wird aktualisiert und gespeichert'],
        ];
    }

    private function wertelisten(): array
    {
        $b = 'Wertelisten';
        return [
            [$b, 'Werteliste anlegen', 'Benutzer ist eingeloggt', 'Werteliste wird mit Name und technischem Namen erstellt'],
            [$b, 'Werteliste bearbeiten', 'Werteliste existiert', 'Name wird aktualisiert'],
            [$b, 'Werteliste löschen (ohne Verwendung)', 'Werteliste existiert, kein Attribut referenziert sie', 'Werteliste wird gelöscht'],
            [$b, 'Werteliste löschen (in Verwendung)', 'Werteliste ist einem Attribut zugewiesen', 'Löschung wird verhindert oder Warnung angezeigt'],
            [$b, 'Wertelisten-Eintrag anlegen', 'Werteliste existiert', 'Eintrag wird mit Label und technischem Namen erstellt'],
            [$b, 'Wertelisten-Eintrag bearbeiten', 'Eintrag existiert', 'Label wird in allen Sprachen aktualisiert'],
            [$b, 'Wertelisten-Eintrag löschen', 'Eintrag existiert', 'Eintrag wird gelöscht, Produkte mit diesem Wert verlieren die Zuordnung'],
            [$b, 'Wertelisten-Einträge sortieren', 'Mehrere Einträge vorhanden', 'Reihenfolge wird aktualisiert und in Auswahllisten korrekt dargestellt'],
            [$b, 'Mehrsprachige Wertelisten-Einträge pflegen', 'Werteliste mit Einträgen vorhanden', 'Labels werden pro Sprache korrekt gespeichert und angezeigt'],
        ];
    }

    private function attributsichten(): array
    {
        $b = 'Attributsichten (Attribute Views)';
        return [
            [$b, 'Attributsicht anlegen', 'Benutzer ist eingeloggt', 'Attributsicht wird mit Name und technischem Namen erstellt'],
            [$b, 'Attributsicht bearbeiten', 'Attributsicht existiert', 'Name wird aktualisiert'],
            [$b, 'Attributsicht löschen', 'Attributsicht existiert', 'Attributsicht wird gelöscht, Zuordnungen werden entfernt'],
            [$b, 'Attribute einer Sicht zuordnen', 'Attributsicht und Attribute existieren', 'Attribute werden der Sicht zugeordnet und in der Sicht angezeigt'],
            [$b, 'Attribut aus Sicht entfernen', 'Attribut ist einer Sicht zugeordnet', 'Zuordnung wird entfernt, Attribut ist nicht mehr in der Sicht sichtbar'],
            [$b, 'Reihenfolge der Attribute in Sicht ändern', 'Mehrere Attribute in einer Sicht', 'Sortierung wird gespeichert und korrekt angezeigt'],
        ];
    }

    private function attributtypen(): array
    {
        $b = 'Attributtypen';
        return [
            [$b, 'Alle Attributtypen auflisten', 'Benutzer ist eingeloggt', 'Liste aller verfügbaren Typen wird angezeigt (Text, Number, Select, Boolean, Date, etc.)'],
            [$b, 'Attributtyp-Details anzeigen', 'Benutzer ist eingeloggt', 'Technischer Name, Validierungsregeln und Konfiguration des Typs werden angezeigt'],
            [$b, 'Attributtyp anlegen', 'Benutzer ist eingeloggt', 'Neuer Attributtyp wird erstellt und ist für Attribute verwendbar'],
        ];
    }

    private function attribute(): array
    {
        $b = 'Attribute';
        return [
            [$b, 'Textattribut anlegen', 'Attributtypen und Einheiten existieren', 'Attribut vom Typ „text" wird mit Name, technischem Namen und Konfiguration erstellt'],
            [$b, 'Zahlenattribut anlegen', 'Einheitengruppe mit Einheiten existiert', 'Attribut vom Typ „number" wird erstellt, Einheit wird zugeordnet'],
            [$b, 'Select-Attribut anlegen', 'Werteliste existiert', 'Attribut vom Typ „select" wird erstellt und mit Werteliste verknüpft'],
            [$b, 'Multi-Select-Attribut anlegen', 'Werteliste existiert', 'Attribut vom Typ „multiselect" wird erstellt und mit Werteliste verknüpft'],
            [$b, 'Boolean-Attribut anlegen', 'Benutzer ist eingeloggt', 'Attribut vom Typ „boolean" wird erstellt'],
            [$b, 'Datumsattribut anlegen', 'Benutzer ist eingeloggt', 'Attribut vom Typ „date" wird erstellt'],
            [$b, 'Textarea-Attribut anlegen', 'Benutzer ist eingeloggt', 'Attribut vom Typ „textarea" wird erstellt, unterstützt Mehrzeilentext'],
            [$b, 'HTML-Attribut anlegen', 'Benutzer ist eingeloggt', 'Attribut vom Typ „html" wird erstellt, unterstützt Rich-Text'],
            [$b, 'Attribut bearbeiten', 'Attribut existiert', 'Name, Beschreibung und Konfiguration werden aktualisiert'],
            [$b, 'Attribut löschen (ohne Produktdaten)', 'Attribut existiert, keine Werte gepflegt', 'Attribut wird gelöscht'],
            [$b, 'Attribut löschen (mit Produktdaten)', 'Attribut hat gepflegte Werte', 'Löschung wird verhindert oder Warnung angezeigt, Daten werden bereinigt'],
            [$b, 'Pflichtfeld-Validierung konfigurieren', 'Attribut existiert', 'Attribut als Pflichtfeld markiert, Vollständigkeitsprüfung berücksichtigt es'],
            [$b, 'Mehrsprachiges Attribut konfigurieren', 'Attribut existiert', 'Attribut als mehrsprachig markiert, pro Sprache separate Werte möglich'],
        ];
    }

    private function produkttypen(): array
    {
        $b = 'Produkttypen';
        return [
            [$b, 'Produkttyp anlegen', 'Attribute existieren', 'Produkttyp wird mit Name und technischem Namen erstellt'],
            [$b, 'Produkttyp bearbeiten', 'Produkttyp existiert', 'Name und Beschreibung werden aktualisiert'],
            [$b, 'Produkttyp löschen (ohne Produkte)', 'Produkttyp existiert, keine Produkte zugeordnet', 'Produkttyp wird gelöscht'],
            [$b, 'Produkttyp löschen (mit Produkten)', 'Produkte nutzen diesen Typ', 'Löschung wird verhindert oder Warnung angezeigt'],
            [$b, 'Attribute einem Produkttyp zuordnen', 'Produkttyp und Attribute existieren', 'Attribute werden dem Produkttyp zugeordnet und bei neuen Produkten verfügbar'],
            [$b, 'Attribut-Zuordnung am Produkttyp entfernen', 'Attribut ist dem Produkttyp zugeordnet', 'Zuordnung wird entfernt'],
        ];
    }

    private function hierarchien(): array
    {
        $b = 'Hierarchien & Knoten';
        return [
            [$b, 'Hierarchie anlegen', 'Benutzer ist eingeloggt', 'Hierarchie wird mit Name, Typ und technischem Namen erstellt'],
            [$b, 'Hierarchie bearbeiten', 'Hierarchie existiert', 'Name und Beschreibung werden aktualisiert'],
            [$b, 'Hierarchie löschen', 'Hierarchie existiert', 'Hierarchie inkl. aller Knoten wird gelöscht'],
            [$b, 'Hierarchie-Knoten anlegen (Root)', 'Hierarchie existiert', 'Root-Knoten wird als erstes Element erstellt'],
            [$b, 'Hierarchie-Knoten anlegen (Kind)', 'Root-Knoten existiert', 'Kind-Knoten wird unter dem Root-Knoten erstellt'],
            [$b, 'Hierarchie-Knoten verschieben', 'Mehrere Knoten existieren', 'Knoten wird an neue Position verschoben, Baumstruktur wird korrekt aktualisiert'],
            [$b, 'Hierarchie-Knoten duplizieren', 'Knoten existiert', 'Knoten wird inkl. Unterknoten dupliziert'],
            [$b, 'Hierarchie-Knoten löschen', 'Knoten existiert', 'Knoten wird gelöscht, Unterknoten werden ggf. ebenfalls entfernt'],
            [$b, 'Baumstruktur abrufen', 'Hierarchie mit Knoten existiert', 'Vollständiger Baum wird korrekt hierarchisch zurückgegeben'],
            [$b, 'Attribute an Hierarchie-Knoten zuweisen', 'Knoten und Attribute existieren', 'Attribute werden dem Knoten zugewiesen, Werte können gepflegt werden'],
            [$b, 'Attributwerte am Hierarchie-Knoten pflegen', 'Attribute am Knoten zugewiesen', 'Werte werden gespeichert und sind bei Vererbung an Produkten verfügbar'],
            [$b, 'Attribut-Sortierung an Knoten ändern (Bulk Sort)', 'Mehrere Attribute am Knoten', 'Reihenfolge wird über Bulk-Sort aktualisiert'],
        ];
    }

    private function produkte(): array
    {
        $b = 'Produkte';
        return [
            [$b, 'Produkt anlegen', 'Produkttyp existiert', 'Produkt wird mit SKU, Name und Produkttyp erstellt, Status ist „draft"'],
            [$b, 'Produkt bearbeiten (Stammdaten)', 'Produkt existiert', 'SKU, Name und Beschreibung werden aktualisiert'],
            [$b, 'Produktattributwerte pflegen', 'Produkt mit Attributen existiert', 'Attributwerte werden gespeichert und korrekt angezeigt'],
            [$b, 'Mehrsprachige Attributwerte pflegen', 'Mehrsprachiges Attribut am Produkt', 'Werte werden pro Sprache separat gespeichert und angezeigt'],
            [$b, 'Produkt-Status auf „active" setzen', 'Produkt im Status „draft"', 'Status wird auf „active" geändert, Produkt ist im Katalog sichtbar'],
            [$b, 'Produkt-Status auf „inactive" setzen', 'Produkt im Status „active"', 'Status wird auf „inactive" geändert, Produkt ist im Katalog nicht mehr sichtbar'],
            [$b, 'Produkt löschen', 'Produkt existiert', 'Produkt wird gelöscht inkl. aller Attributwerte, Preise, Beziehungen'],
            [$b, 'Produkt duplizieren', 'Produkt mit Attributwerten existiert', 'Kopie des Produkts wird erstellt mit neuer SKU, alle Werte werden übernommen'],
            [$b, 'Produkt einer Hierarchie zuordnen', 'Produkt und Hierarchie-Knoten existieren', 'Produkt wird dem Knoten zugeordnet, ist in der Baumstruktur sichtbar'],
            [$b, 'Produkt-Hierarchie-Zuordnung entfernen', 'Produkt ist einem Knoten zugeordnet', 'Zuordnung wird entfernt'],
            [$b, 'Produkt einer Output-Hierarchie zuordnen', 'Output-Hierarchie existiert', 'Produkt wird der Output-Hierarchie zugeordnet'],
            [$b, 'Produkt-Vollständigkeit prüfen', 'Produkt mit Pflichtfeldern existiert', 'Vollständigkeitsgrad wird in Prozent angezeigt, fehlende Pflichtfelder werden aufgelistet'],
            [$b, 'Aufgelöste Attributwerte prüfen (Vererbung)', 'Produkt in Hierarchie mit vererbten Werten', 'Vererbte und direkte Werte werden korrekt zusammengeführt angezeigt'],
            [$b, 'Produkte vergleichen', 'Mindestens 2 Produkte existieren', 'Attributwerte beider Produkte werden nebeneinander dargestellt, Unterschiede hervorgehoben'],
            [$b, 'Produktliste mit Paginierung', 'Viele Produkte existieren', 'Produkte werden paginiert angezeigt, Seiten können gewechselt werden'],
            [$b, 'Produktliste filtern nach Status', 'Produkte mit verschiedenen Status existieren', 'Nur Produkte mit gewähltem Status werden angezeigt'],
        ];
    }

    private function preise(): array
    {
        $b = 'Preistypen & Produktpreise';
        return [
            [$b, 'Preistyp anlegen', 'Benutzer ist eingeloggt', 'Preistyp wird mit Name und technischem Namen erstellt'],
            [$b, 'Preistyp bearbeiten', 'Preistyp existiert', 'Name und Währung werden aktualisiert'],
            [$b, 'Preistyp löschen', 'Preistyp existiert, keine Preise gepflegt', 'Preistyp wird gelöscht'],
            [$b, 'Preis am Produkt anlegen', 'Produkt und Preistyp existieren', 'Preis wird mit Betrag und Währung am Produkt gespeichert'],
            [$b, 'Preis am Produkt ändern', 'Preis existiert', 'Betrag wird aktualisiert'],
            [$b, 'Preis am Produkt löschen', 'Preis existiert', 'Preis wird entfernt'],
            [$b, 'Mehrere Preistypen am Produkt pflegen', 'Mehrere Preistypen existieren', 'Verschiedene Preise (VK, EK, UVP) werden korrekt gespeichert und angezeigt'],
            [$b, 'Preise mit Staffelung pflegen', 'Produkt mit Preistyp existiert', 'Staffelpreise werden korrekt gespeichert und beim Export berücksichtigt'],
        ];
    }

    private function beziehungen(): array
    {
        $b = 'Beziehungstypen & Produktbeziehungen';
        return [
            [$b, 'Beziehungstyp anlegen', 'Benutzer ist eingeloggt', 'Beziehungstyp wird mit Name und technischem Namen erstellt (z.B. Zubehör, Ersatzteil)'],
            [$b, 'Beziehungstyp bearbeiten', 'Beziehungstyp existiert', 'Name wird aktualisiert'],
            [$b, 'Beziehungstyp löschen', 'Beziehungstyp existiert, keine Beziehungen gepflegt', 'Beziehungstyp wird gelöscht'],
            [$b, 'Produktbeziehung anlegen', 'Zwei Produkte und ein Beziehungstyp existieren', 'Beziehung wird zwischen den Produkten erstellt'],
            [$b, 'Produktbeziehung anzeigen', 'Beziehung existiert', 'Verknüpfte Produkte werden mit Beziehungstyp angezeigt'],
            [$b, 'Produktbeziehung löschen', 'Beziehung existiert', 'Beziehung wird entfernt, Produkte existieren weiterhin'],
            [$b, 'Bidirektionale Beziehung prüfen', 'Beziehung wurde angelegt', 'Beziehung ist von beiden Produkten aus sichtbar'],
            [$b, 'Mehrere Beziehungstypen am Produkt', 'Verschiedene Beziehungstypen existieren', 'Produkt hat Beziehungen verschiedener Typen, alle korrekt gruppiert angezeigt'],
        ];
    }

    private function medien(): array
    {
        $b = 'Medien & Assets';
        return [
            [$b, 'Medienverwendungstyp anlegen', 'Benutzer ist eingeloggt', 'Verwendungstyp wird erstellt (z.B. Teaser, Galerie, Datenblatt)'],
            [$b, 'Medienverwendungstyp bearbeiten', 'Verwendungstyp existiert', 'Name wird aktualisiert'],
            [$b, 'Medienverwendungstyp löschen', 'Verwendungstyp existiert', 'Verwendungstyp wird gelöscht'],
            [$b, 'Medium hochladen (Bild)', 'Benutzer ist eingeloggt', 'Bild wird hochgeladen, Thumbnail wird generiert, Metadaten werden extrahiert'],
            [$b, 'Medium hochladen (PDF)', 'Benutzer ist eingeloggt', 'PDF wird hochgeladen und gespeichert'],
            [$b, 'Medium-Metadaten pflegen (EAV)', 'Medium existiert', 'Attributwerte am Medium werden gespeichert'],
            [$b, 'Thumbnail abrufen', 'Bild-Medium existiert', 'Thumbnail wird in korrekter Größe zurückgegeben'],
            [$b, 'Medium an Produkt zuordnen', 'Medium und Produkt existieren', 'Medium wird dem Produkt zugeordnet mit Verwendungstyp und Sortierung'],
            [$b, 'Medium-Zuordnung am Produkt entfernen', 'Medium ist Produkt zugeordnet', 'Zuordnung wird entfernt, Medium existiert weiterhin'],
            [$b, 'Alle Medien eines Produkts anzeigen', 'Produkt hat mehrere Medien', 'Alle zugeordneten Medien werden mit Typ und Sortierung angezeigt'],
            [$b, 'Asset-Katalog: Medien durchsuchen', 'Medien existieren', 'Medien werden in Ordnerstruktur angezeigt, filterbar'],
            [$b, 'Asset-Katalog: Medium herunterladen', 'Medium existiert', 'Original-Datei wird heruntergeladen'],
            [$b, 'Medien-Diagnose ausführen', 'Medien existieren', 'Diagnose prüft Integrität aller Medien, fehlerhafte werden gemeldet'],
        ];
    }

    private function varianten(): array
    {
        $b = 'Varianten & Vererbung';
        return [
            [$b, 'Variante manuell anlegen', 'Elternprodukt existiert', 'Variante wird erstellt mit eigener SKU, verweist auf Elternprodukt'],
            [$b, 'Variante bearbeiten', 'Variante existiert', 'Eigene Attributwerte der Variante werden gespeichert'],
            [$b, 'Variante löschen', 'Variante existiert', 'Variante wird gelöscht, Elternprodukt bleibt bestehen'],
            [$b, 'Varianten automatisch generieren', 'Elternprodukt mit variierenden Attributen existiert', 'Varianten werden für alle Kombinationen generiert'],
            [$b, 'Vererbungsregeln definieren', 'Elternprodukt mit Varianten existiert', 'Regeln legen fest, welche Attribute vererbt und welche überschrieben werden'],
            [$b, 'Vererbungsregeln anzeigen', 'Regeln existieren', 'Aktuelle Vererbungskonfiguration wird korrekt angezeigt'],
            [$b, 'Vererbte Werte an Variante prüfen', 'Vererbungsregeln definiert', 'Vererbte Werte vom Elternprodukt werden an der Variante angezeigt'],
            [$b, 'Vererbten Wert an Variante überschreiben', 'Vererbter Wert existiert', 'Eigener Wert überschreibt den vererbten, wird als überschrieben markiert'],
            [$b, 'Überschriebenen Wert auf Vererbung zurücksetzen', 'Wert ist überschrieben', 'Wert wird wieder vom Elternprodukt geerbt'],
            [$b, 'Hierarchie-Vererbung prüfen', 'Produkt in Hierarchie mit Knotenwerten', 'Werte vom Hierarchie-Knoten werden korrekt an Produkte vererbt'],
        ];
    }

    private function versionierung(): array
    {
        $b = 'Produktversionierung';
        return [
            [$b, 'Version erstellen', 'Produkt mit Attributwerten existiert', 'Snapshot der aktuellen Werte wird als Version gespeichert'],
            [$b, 'Versionen eines Produkts auflisten', 'Mehrere Versionen existieren', 'Alle Versionen werden mit Datum und Ersteller angezeigt'],
            [$b, 'Version anzeigen', 'Version existiert', 'Alle Attributwerte der Version werden korrekt dargestellt'],
            [$b, 'Versionen vergleichen', 'Mindestens 2 Versionen existieren', 'Unterschiede zwischen den Versionen werden hervorgehoben'],
            [$b, 'Version aktivieren', 'Inaktive Version existiert', 'Produkt wird auf den Stand der Version zurückgesetzt'],
            [$b, 'Version zur Aktivierung planen', 'Version existiert', 'Aktivierungszeitpunkt wird gespeichert, Version wird zum Zeitpunkt automatisch aktiv'],
            [$b, 'Geplante Aktivierung stornieren', 'Geplante Version existiert', 'Planung wird entfernt, Version bleibt inaktiv'],
            [$b, 'Zu früherer Version zurückkehren', 'Mehrere Versionen existieren', 'Produkt wird auf den Stand der gewählten Version zurückgesetzt'],
        ];
    }

    private function massendatenpflege(): array
    {
        $b = 'Massendatenpflege (Bulk)';
        return [
            [$b, 'Bulk Editor: Mehrere Produkte laden', 'Mehrere Produkte existieren', 'Attributwerte aller gewählten Produkte werden in Tabellenform angezeigt'],
            [$b, 'Bulk Editor: Attributwerte ändern', 'Produkte im Editor geladen', 'Geänderte Werte werden markiert, noch nicht gespeichert'],
            [$b, 'Bulk Editor: Änderungen speichern', 'Änderungen im Editor vorgenommen', 'Alle geänderten Werte werden gespeichert, Erfolgsmeldung wird angezeigt'],
            [$b, 'Bulk Update: Preview anzeigen', 'Produkte und Update-Regel definiert', 'Zusammenfassung der geplanten Änderungen wird angezeigt (Dry-Run)'],
            [$b, 'Bulk Update: Attribute massenweise ändern', 'Preview bestätigt', 'Attributwerte werden für alle gewählten Produkte aktualisiert'],
            [$b, 'Bulk Update: Relationen massenweise ändern', 'Produkte und Beziehungstyp existieren', 'Beziehungen werden für alle gewählten Produkte hinzugefügt/entfernt'],
            [$b, 'Bulk Update: Output-Hierarchie massenweise zuweisen', 'Output-Hierarchie und Produkte existieren', 'Produkte werden der Output-Hierarchie zugeordnet'],
            [$b, 'Bulk Update: Status massenweise ändern', 'Mehrere Produkte existieren', 'Status aller gewählten Produkte wird geändert'],
        ];
    }

    private function importFunktionen(): array
    {
        $b = 'Import';
        return [
            [$b, 'Import-Template herunterladen (Excel)', 'Benutzer ist eingeloggt', 'Excel-Template mit allen 14 Sheets wird heruntergeladen (Produkttypen bis Medien)'],
            [$b, 'Import-Template herunterladen (Demo)', 'Benutzer ist eingeloggt', 'Demo-Template mit Beispieldaten wird heruntergeladen'],
            [$b, 'Import-Profil anlegen', 'Benutzer ist eingeloggt', 'Profil wird mit Name und Mapping-Konfiguration erstellt'],
            [$b, 'Import-Profil bearbeiten', 'Profil existiert', 'Mapping und Konfiguration werden aktualisiert'],
            [$b, 'Import-Profil löschen', 'Profil existiert', 'Profil wird gelöscht'],
            [$b, 'Dateistruktur analysieren', 'Excel-Datei vorhanden', 'Spalten und Datentypen werden erkannt, Mapping-Vorschläge werden generiert'],
            [$b, 'Attribute automatisch generieren', 'Datei analysiert', 'Fehlende Attribute werden aus Spaltenüberschriften automatisch angelegt'],
            [$b, 'Import-Datei hochladen', 'Import-Profil oder Standard-Format', 'Datei wird hochgeladen, Import-Job wird erstellt'],
            [$b, 'Import-Vorschau prüfen', 'Datei hochgeladen', 'Vorschau zeigt zu importierende Daten, Warnungen und Fehler'],
            [$b, 'Import ausführen', 'Vorschau geprüft', 'Daten werden importiert, Fortschritt wird angezeigt'],
            [$b, 'Import-Ergebnis prüfen', 'Import abgeschlossen', 'Anzahl erstellter/aktualisierter/fehlerhafter Datensätze wird angezeigt'],
            [$b, 'Import-Logs einsehen', 'Import abgeschlossen', 'Detaillierte Logs pro Zeile werden angezeigt'],
            [$b, 'Fehler-Datei herunterladen', 'Import mit Fehlern abgeschlossen', 'Excel mit fehlerhaften Zeilen und Fehlerbeschreibung wird heruntergeladen'],
            [$b, 'JSON-Import ausführen', 'JSON-Datei im korrekten Format', 'Alle Sektionen werden abhängigkeitsgerecht importiert'],
            [$b, 'JSON-Import validieren (ohne Ausführung)', 'JSON-Datei vorhanden', 'Validierung meldet Fehler, ohne Daten zu schreiben'],
            [$b, 'Import abbrechen/löschen', 'Laufender oder abgeschlossener Import', 'Import wird gestoppt oder Job wird gelöscht'],
        ];
    }

    private function exportFunktionen(): array
    {
        $b = 'Export';
        return [
            [$b, 'Export-Profil anlegen', 'Benutzer ist eingeloggt', 'Profil wird mit Attributsicht, Sprachen und Flatten-Modus erstellt'],
            [$b, 'Export-Profil bearbeiten', 'Profil existiert', 'Konfiguration wird aktualisiert'],
            [$b, 'Export-Profil löschen', 'Profil existiert', 'Profil wird gelöscht'],
            [$b, 'Export-Profil ausführen', 'Profil existiert', 'Export wird mit Profil-Konfiguration generiert'],
            [$b, 'Export-Job anlegen', 'Benutzer ist eingeloggt', 'Job wird mit Namen und Konfiguration erstellt'],
            [$b, 'Export-Job mit Zeitplan konfigurieren', 'Export-Job existiert', 'Cron-Ausdruck wird gespeichert, Job läuft automatisch'],
            [$b, 'Export-Job manuell ausführen', 'Job existiert', 'Export wird gestartet, Ergebnis steht zum Download bereit'],
            [$b, 'Export-Ergebnis herunterladen', 'Export abgeschlossen', 'Datei wird im konfigurierten Format heruntergeladen'],
            [$b, 'Excel-Export mit konfigurierbaren Spalten', 'Produkte existieren', 'XLSX-Datei wird mit gewählten Spalten und Filtern generiert'],
            [$b, 'JSON-Vollexport', 'Daten existieren', 'Vollständiger JSON-Export aller PIM-Daten wird generiert'],
            [$b, 'JSON-Export gefiltert (Sektionen)', 'Daten existieren', 'Nur gewählte Sektionen werden exportiert'],
            [$b, 'CSV-Export', 'Produkte existieren', 'CSV-Datei wird korrekt formatiert generiert'],
            [$b, 'XML-Export', 'Produkte existieren', 'XML-Datei wird korrekt strukturiert generiert'],
            [$b, 'PQL-basierter Export', 'Produkte existieren', 'Nur Produkte, die PQL-Abfrage matchen, werden exportiert'],
            [$b, 'Delta-Export (nur Änderungen)', 'Produkte seit letztem Export geändert', 'Nur geänderte Produkte werden exportiert (updated_after Filter)'],
            [$b, 'Export mit Medien/Preisen/Relationen', 'Vollständige Produktdaten gepflegt', 'Alle zugehörigen Daten werden im Export eingeschlossen'],
            [$b, 'Mehrsprachiger Export', 'Mehrsprachige Daten gepflegt', 'Daten werden in allen gewählten Sprachen exportiert'],
        ];
    }

    private function suchePql(): array
    {
        $b = 'Suche & PQL';
        return [
            [$b, 'Produktsuche mit Freitext', 'Produkte existieren', 'Produkte werden anhand von SKU, Name und EAN gefunden'],
            [$b, 'Produktsuche mit Attributfiltern', 'Produkte mit Attributwerten existieren', 'Nur Produkte mit passenden Attributwerten werden angezeigt'],
            [$b, 'Suchbare Attribute anzeigen', 'Attribute existieren', 'Liste aller für die Suche verfügbaren Attribute wird angezeigt'],
            [$b, 'Suchprofil anlegen', 'Benutzer ist eingeloggt', 'Suchprofil wird mit Filtern und Sortierung gespeichert'],
            [$b, 'Suchprofil bearbeiten', 'Suchprofil existiert', 'Filter werden aktualisiert'],
            [$b, 'Suchprofil löschen', 'Suchprofil existiert', 'Suchprofil wird gelöscht'],
            [$b, 'PQL-Abfrage ausführen', 'Produkte existieren', 'PQL-Syntax wird verarbeitet, passende Produkte werden zurückgegeben'],
            [$b, 'PQL-Abfrage validieren', 'PQL-Ausdruck eingegeben', 'Syntax wird geprüft, Fehler werden gemeldet'],
            [$b, 'PQL-Abfrage erklären', 'PQL-Ausdruck eingegeben', 'Natürlichsprachliche Erklärung der Abfrage wird angezeigt'],
            [$b, 'PQL-Ergebnisse zählen', 'PQL-Ausdruck eingegeben', 'Anzahl der Treffer wird zurückgegeben, ohne Daten zu laden'],
            [$b, 'Phonetische Suche (sounds like)', 'Produkte existieren', 'Produkte werden auch bei phonetisch ähnlicher Eingabe gefunden'],
            [$b, 'Fuzzy-Suche (Ähnlichkeitssuche)', 'Produkte existieren', 'Produkte werden trotz Tippfehlern gefunden'],
        ];
    }

    private function reports(): array
    {
        $b = 'Reports';
        return [
            [$b, 'Report-Template anlegen', 'Benutzer ist eingeloggt', 'Template wird mit Layout, Feldern und Seitenformat erstellt'],
            [$b, 'Report-Template bearbeiten', 'Template existiert', 'Layout und Felder werden aktualisiert'],
            [$b, 'Report-Template löschen', 'Template existiert', 'Template wird gelöscht'],
            [$b, 'Verfügbare Report-Felder anzeigen', 'Benutzer ist eingeloggt', 'Alle für Reports verfügbaren Felder werden aufgelistet'],
            [$b, 'Report-Vorschau generieren', 'Template existiert', 'Vorschau des Reports mit Beispieldaten wird angezeigt'],
            [$b, 'Report als PDF generieren', 'Template und Produkte existieren', 'PDF-Datei wird generiert und kann heruntergeladen werden'],
            [$b, 'Report als DOCX generieren', 'Template und Produkte existieren', 'Word-Datei wird generiert und kann heruntergeladen werden'],
            [$b, 'Report-Job-Status prüfen', 'Report-Generierung gestartet', 'Fortschritt und Status des Report-Jobs werden angezeigt'],
        ];
    }

    private function merkliste(): array
    {
        $b = 'Merkliste (Watchlist)';
        return [
            [$b, 'Produkt zur Merkliste hinzufügen', 'Produkt existiert', 'Produkt wird der Merkliste hinzugefügt'],
            [$b, 'Merkliste anzeigen', 'Produkte auf der Merkliste', 'Alle gemerkten Produkte werden aufgelistet'],
            [$b, 'Produkt-IDs der Merkliste abrufen', 'Produkte auf der Merkliste', 'Liste der Produkt-IDs wird zurückgegeben'],
            [$b, 'Produkte massenweise zur Merkliste hinzufügen', 'Mehrere Produkte existieren', 'Alle gewählten Produkte werden hinzugefügt'],
            [$b, 'Produkte massenweise von Merkliste entfernen', 'Produkte auf der Merkliste', 'Gewählte Produkte werden entfernt'],
            [$b, 'Merkliste komplett leeren', 'Produkte auf der Merkliste', 'Merkliste ist leer'],
            [$b, 'Merkliste als Excel exportieren', 'Produkte auf der Merkliste', 'XLSX-Datei mit Produktdaten wird heruntergeladen'],
            [$b, 'Merkliste als PDF exportieren', 'Produkte auf der Merkliste', 'PDF mit Produktdaten wird heruntergeladen'],
            [$b, 'Merkliste als PDF-ZIP exportieren', 'Produkte auf der Merkliste', 'ZIP-Datei mit einzelnen PDFs pro Produkt wird heruntergeladen'],
            [$b, 'Merkliste als XLIFF exportieren', 'Produkte auf der Merkliste', 'XLIFF-Datei für Übersetzungen wird heruntergeladen'],
        ];
    }

    private function publixxIntegration(): array
    {
        $b = 'Publixx Integration & PXF-Templates';
        return [
            [$b, 'Publixx Export-Mapping anlegen', 'Attribute und Produkte existieren', 'Mapping zwischen PIM-Attributen und Publixx-Feldern wird erstellt'],
            [$b, 'Publixx Dataset abrufen (alle Produkte)', 'Mapping existiert', 'Alle Produkte werden im Publixx-Format zurückgegeben'],
            [$b, 'Publixx Dataset abrufen (einzelnes Produkt)', 'Mapping und Produkt existieren', 'Einzelnes Produkt wird im Publixx-Format zurückgegeben'],
            [$b, 'Publixx Dataset mit PQL filtern', 'Mapping existiert', 'Nur PQL-gefilterte Produkte werden zurückgegeben'],
            [$b, 'Publixx Webhook empfangen', 'Webhook-Endpunkt konfiguriert', 'Webhook wird verarbeitet, entsprechende Aktion ausgelöst'],
            [$b, 'PXF-Template anlegen', 'Benutzer ist eingeloggt', 'PXF-Template wird mit Layout und Feldern erstellt'],
            [$b, 'PXF-Template importieren', 'PXF-Datei vorhanden', 'Template wird aus Datei importiert'],
            [$b, 'PXF-Template bearbeiten', 'Template existiert', 'Layout und Felder werden aktualisiert'],
            [$b, 'PXF-Template löschen', 'Template existiert', 'Template wird gelöscht'],
            [$b, 'PXF-Template Preview mit Produkt', 'Template und Produkt existieren', 'Vorschau des Templates mit Produktdaten wird generiert'],
        ];
    }

    private function preview(): array
    {
        $b = 'Preview';
        return [
            [$b, 'Produkt-Preview anzeigen', 'Produkt mit Attributwerten existiert', 'Vollständige Produktvorschau mit allen Daten wird angezeigt'],
            [$b, 'Produkt-Preview als Excel exportieren', 'Produkt existiert', 'XLSX mit allen Produktdaten wird heruntergeladen'],
            [$b, 'Produkt-Preview als PDF exportieren', 'Produkt existiert', 'PDF-Datenblatt des Produkts wird generiert'],
            [$b, 'PXF-Template Preview', 'PXF-Template und Produkt existieren', 'Produkt wird in PXF-Template-Darstellung angezeigt'],
            [$b, 'Produkt im Publixx-Dataset-Format anzeigen', 'Export-Mapping existiert', 'Produkt wird im PXF-Dataset-Format dargestellt'],
        ];
    }

    private function benutzerverwaltung(): array
    {
        $b = 'Benutzerverwaltung';
        return [
            [$b, 'Benutzer anlegen', 'Admin ist eingeloggt', 'Benutzer wird mit Name, E-Mail und Passwort erstellt'],
            [$b, 'Benutzer bearbeiten', 'Benutzer existiert', 'Name, E-Mail und Sprache werden aktualisiert'],
            [$b, 'Benutzer deaktivieren', 'Benutzer existiert und ist aktiv', 'Benutzer kann sich nicht mehr einloggen'],
            [$b, 'Benutzer aktivieren', 'Benutzer ist deaktiviert', 'Benutzer kann sich wieder einloggen'],
            [$b, 'Benutzer löschen', 'Benutzer existiert', 'Benutzer wird gelöscht'],
            [$b, 'Rolle anlegen', 'Admin ist eingeloggt', 'Rolle wird mit Name erstellt'],
            [$b, 'Rolle bearbeiten', 'Rolle existiert', 'Name wird aktualisiert'],
            [$b, 'Rolle löschen', 'Rolle existiert, keine Benutzer zugeordnet', 'Rolle wird gelöscht'],
            [$b, 'Berechtigungen einer Rolle zuweisen', 'Rolle existiert', 'Berechtigungen werden zugewiesen, Benutzer der Rolle erhalten entsprechende Rechte'],
            [$b, 'Berechtigung entzogen: Zugriff verweigert', 'Benutzer hat keine Berechtigung für Aktion', 'HTTP 403 wird zurückgegeben, Aktion wird nicht ausgeführt'],
        ];
    }

    private function uebersetzungen(): array
    {
        $b = 'Übersetzungen (XLIFF)';
        return [
            [$b, 'XLIFF-Export für Übersetzung', 'Mehrsprachige Daten existieren', 'XLIFF-Datei mit Quell- und Zielsprache wird generiert'],
            [$b, 'XLIFF-Import nach Übersetzung', 'Übersetzte XLIFF-Datei vorhanden', 'Übersetzte Werte werden in die Zielsprache importiert'],
            [$b, 'XLIFF-Export mit Filterung', 'Verschiedene Produkte mit Übersetzungen', 'Nur gewählte Produkte/Attribute werden in XLIFF exportiert'],
            [$b, 'XLIFF-Roundtrip prüfen', 'XLIFF exportiert und importiert', 'Exportierte und reimportierte Daten stimmen überein'],
        ];
    }

    private function woerterbuch(): array
    {
        $b = 'Wörterbuch (Dictionary)';
        return [
            [$b, 'Wörterbuch-Eintrag anlegen', 'Benutzer ist eingeloggt', 'Eintrag wird mit Schlüssel und Übersetzungen erstellt'],
            [$b, 'Wörterbuch-Eintrag bearbeiten', 'Eintrag existiert', 'Übersetzungen werden aktualisiert'],
            [$b, 'Wörterbuch-Eintrag löschen', 'Eintrag existiert', 'Eintrag wird gelöscht'],
            [$b, 'Wörterbuch-Einträge auflisten', 'Einträge existieren', 'Alle Einträge werden mit Schlüssel und Sprachen angezeigt'],
        ];
    }

    private function einstellungenAdmin(): array
    {
        $b = 'Einstellungen & Administration';
        return [
            [$b, 'Katalog-Theme konfigurieren', 'Admin ist eingeloggt', 'Theme-Einstellungen werden gespeichert (Farben, Logo, etc.)'],
            [$b, 'Katalog-Einstellungen abrufen (öffentlich)', 'Katalog konfiguriert', 'Aktuelle Katalog-Einstellungen werden ohne Auth zurückgegeben'],
            [$b, 'Demo-Daten laden', 'Leeres oder bestehendes System', 'Demo-Daten werden geladen, System ist sofort nutzbar'],
            [$b, 'Datenmodell zurücksetzen', 'Daten existieren', 'Alle PIM-Daten werden gelöscht, System ist leer'],
            [$b, 'Deployment-Status prüfen', 'Admin ist eingeloggt', 'Aktueller Deployment-Status wird angezeigt'],
            [$b, 'Deployment ausführen', 'Admin ist eingeloggt', 'Deployment wird gestartet, neuer Stand wird veröffentlicht'],
            [$b, 'Deployment-Rollback', 'Deployment wurde ausgeführt', 'System wird auf vorherigen Stand zurückgesetzt'],
        ];
    }

    private function katalogApi(): array
    {
        $b = 'Katalog-API (öffentlich)';
        return [
            [$b, 'Katalog: Produkte auflisten', 'Aktive Produkte im Katalog', 'Nur aktive Produkte werden ohne Authentifizierung zurückgegeben'],
            [$b, 'Katalog: Produkt-Details abrufen', 'Aktives Produkt existiert', 'Vollständige Produktdaten werden zurückgegeben'],
            [$b, 'Katalog: Kategorien anzeigen', 'Hierarchien mit Produkten existieren', 'Kategoriestruktur wird korrekt zurückgegeben'],
            [$b, 'Katalog: Inaktive Produkte sind nicht sichtbar', 'Inaktive Produkte existieren', 'Inaktive Produkte werden über die Katalog-API nicht zurückgegeben'],
        ];
    }

    private function auditLog(): array
    {
        $b = 'Audit-Log';
        return [
            [$b, 'Änderungsprotokoll prüfen', 'Änderungen wurden vorgenommen', 'Alle Änderungen werden mit Benutzer, Zeitstempel und Aktion protokolliert'],
            [$b, 'Audit-Log nach Benutzer filtern', 'Mehrere Benutzer haben Änderungen vorgenommen', 'Nur Änderungen des gewählten Benutzers werden angezeigt'],
            [$b, 'Audit-Log: Alte und neue Werte vergleichen', 'Attributwert wurde geändert', 'Alter und neuer Wert werden nebeneinander angezeigt'],
            [$b, 'Debug-Logs einsehen', 'Admin ist eingeloggt', 'System-Logs werden angezeigt, filterbar nach Level und Zeitraum'],
        ];
    }
}
