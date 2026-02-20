<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Erzeugt ein Excel-Import-Template mit realistischen Demodaten
 * für alle 14 Reiter, basierend auf einem Elektronik-/Consumer-Goods-Sortiment.
 */
class DemoTemplateGenerator
{
    /**
     * Header-Definitionen (identisch mit TemplateGenerator).
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
     * Demodaten pro Sheet (ab Zeile 2).
     * Jedes Array-Element ist eine Zeile, die Spalten werden in Reihenfolge A, B, C... befüllt.
     */
    private function getDemoData(): array
    {
        return [
            // ── 01 Produkttypen ─────────────────────────────────────────
            '01_Produkttypen' => [
                ['elektronik',  'Elektronik',  'Electronics', 'Elektronische Geräte und Unterhaltungselektronik', 'Ja', 'Ja', 'Ja', 'Ja'],
                ['zubehoer',    'Zubehör',     'Accessories', 'Zubehörteile und Peripheriegeräte',               'Nein', 'Ja', 'Ja', 'Ja'],
                ['software',    'Software',    'Software',    'Softwareprodukte und Lizenzen',                    'Nein', 'Nein', 'Ja', 'Nein'],
            ],

            // ── 02 Attributgruppen ──────────────────────────────────────
            '02_Attributgruppen' => [
                ['allgemein',       'Allgemein',        'General',        'Allgemeine Produkteigenschaften',   1],
                ['technische_daten','Technische Daten', 'Technical Data', 'Technische Spezifikationen',        2],
                ['abmessungen',     'Abmessungen',      'Dimensions',     'Maße und Gewicht',                  3],
                ['marketing',       'Marketing',        'Marketing',      'Marketingrelevante Informationen',  4],
                ['konnektivitaet',  'Konnektivität',    'Connectivity',   'Verbindungsoptionen und Schnittstellen', 5],
            ],

            // ── 03 Einheiten ────────────────────────────────────────────
            '03_Einheiten' => [
                ['gewicht', 'Gewicht',  'gramm',      'g',   1,       'Ja'],
                ['gewicht', 'Gewicht',  'kilogramm',  'kg',  1000,    'Nein'],
                ['laenge',  'Länge',    'millimeter',  'mm',  1,       'Ja'],
                ['laenge',  'Länge',    'zentimeter',  'cm',  10,      'Nein'],
                ['laenge',  'Länge',    'meter',       'm',   1000,    'Nein'],
                ['speicher','Speicher', 'megabyte',    'MB',  1,       'Ja'],
                ['speicher','Speicher', 'gigabyte',    'GB',  1024,    'Nein'],
                ['speicher','Speicher', 'terabyte',    'TB',  1048576, 'Nein'],
                ['zeit',    'Zeit',     'stunde',      'h',   1,       'Ja'],
                ['zeit',    'Zeit',     'minute',      'min', null,    'Nein'],
                ['leistung','Leistung', 'watt',        'W',   1,       'Ja'],
                ['leistung','Leistung', 'kilowatt',    'kW',  1000,    'Nein'],
            ],

            // ── 04 Wertelisten ──────────────────────────────────────────
            '04_Wertelisten' => [
                // Farben
                ['farben',          'Farben',            'schwarz',    'Schwarz',     'Black',         1],
                ['farben',          'Farben',            'weiss',      'Weiß',        'White',         2],
                ['farben',          'Farben',            'silber',     'Silber',      'Silver',        3],
                ['farben',          'Farben',            'blau',       'Blau',        'Blue',          4],
                ['farben',          'Farben',            'rot',        'Rot',         'Red',           5],
                ['farben',          'Farben',            'gruen',      'Grün',        'Green',         6],
                // Materialien
                ['materialien',     'Materialien',       'kunststoff', 'Kunststoff',  'Plastic',       1],
                ['materialien',     'Materialien',       'aluminium',  'Aluminium',   'Aluminum',      2],
                ['materialien',     'Materialien',       'stahl',      'Stahl',       'Steel',         3],
                ['materialien',     'Materialien',       'glas',       'Glas',        'Glass',         4],
                ['materialien',     'Materialien',       'holz',       'Holz',        'Wood',          5],
                // Energieeffizienzklasse
                ['energieeffizienz','Energieeffizienz',  'a_plus_plus_plus', 'A+++', 'A+++', 1],
                ['energieeffizienz','Energieeffizienz',  'a_plus_plus',      'A++',  'A++',  2],
                ['energieeffizienz','Energieeffizienz',  'a_plus',           'A+',   'A+',   3],
                ['energieeffizienz','Energieeffizienz',  'a',                'A',    'A',    4],
                ['energieeffizienz','Energieeffizienz',  'b',                'B',    'B',    5],
                // Anschlusstypen
                ['anschlusstypen',  'Anschlusstypen',    'usb_c',      'USB-C',        'USB-C',       1],
                ['anschlusstypen',  'Anschlusstypen',    'usb_a',      'USB-A',        'USB-A',       2],
                ['anschlusstypen',  'Anschlusstypen',    'hdmi',       'HDMI',         'HDMI',        3],
                ['anschlusstypen',  'Anschlusstypen',    'klinke_35',  '3,5mm Klinke', '3.5mm Jack',  4],
                ['anschlusstypen',  'Anschlusstypen',    'bluetooth',  'Bluetooth',    'Bluetooth',   5],
                // Betriebssysteme
                ['betriebssysteme', 'Betriebssysteme',   'windows',    'Windows',      'Windows',     1],
                ['betriebssysteme', 'Betriebssysteme',   'macos',      'macOS',        'macOS',       2],
                ['betriebssysteme', 'Betriebssysteme',   'linux',      'Linux',        'Linux',       3],
                ['betriebssysteme', 'Betriebssysteme',   'android',    'Android',      'Android',     4],
                ['betriebssysteme', 'Betriebssysteme',   'ios',        'iOS',          'iOS',         5],
            ],

            // ── 05 Attribute ────────────────────────────────────────────
            '05_Attribute' => [
                // Allgemeine Attribute
                //  tech_name,          name_de,               name_en,              desc,                                           type,        group,              vlist,             ugroup, udefault, multipliable, max_mult, translatable, mandatory, unique, searchable, inheritable, parent, source, views
                ['beschreibung',        'Beschreibung',        'Description',        'Ausführliche Produktbeschreibung',              'String',    'allgemein',        null,              null,   null,     'Nein',       null,     'Ja',         'Pflicht', 'Nein', 'Ja',       'Ja',        null,   'PIM',  null],
                ['kurzbeschreibung',    'Kurzbeschreibung',    'Short Description',  'Kurze Produktzusammenfassung',                  'String',    'allgemein',        null,              null,   null,     'Nein',       null,     'Ja',         'Optional','Nein', 'Ja',       'Ja',        null,   'PIM',  null],
                ['herstellerland',      'Herstellerland',      'Country of Origin',  'Land der Herstellung',                          'String',    'allgemein',        null,              null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Ja',        null,   'PIM',  null],
                ['garantie_jahre',      'Garantie (Jahre)',    'Warranty (Years)',   'Garantiedauer in Jahren',                       'Number',    'allgemein',        null,              null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Ja',        null,   'PIM',  null],

                // Technische Daten
                ['farbe',               'Farbe',               'Color',              'Produktfarbe',                                  'Selection', 'technische_daten', 'farben',          null,   null,     'Nein',       null,     'Nein',       'Pflicht', 'Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['material',            'Material',            'Material',           'Hauptmaterial des Produkts',                    'Selection', 'technische_daten', 'materialien',     null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Ja',        null,   'PIM',  null],
                ['energieeffizienzklasse','Energieeffizienzklasse','Energy Class',    'EU-Energieeffizienzklasse',                     'Selection', 'technische_daten', 'energieeffizienz',null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Ja',        null,   'PIM',  null],
                ['anschluss',           'Anschluss',           'Connection',         'Verfügbare Anschlüsse',                         'Selection', 'konnektivitaet',  'anschlusstypen',  null,   null,     'Ja',         5,        'Nein',       'Optional','Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['bluetooth_version',   'Bluetooth Version',   'Bluetooth Version',  'Bluetooth-Versionsnummer',                      'String',    'konnektivitaet',  null,              null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['betriebssystem',      'Betriebssystem',      'Operating System',   'Installiertes Betriebssystem',                  'Selection', 'technische_daten', 'betriebssysteme', null,   null,     'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['speicherkapazitaet',  'Speicherkapazität',   'Storage Capacity',   'Interner Speicher',                             'Number',    'technische_daten', null,              'speicher','GB',  'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['akkulaufzeit',        'Akkulaufzeit',        'Battery Life',       'Akkubetriebsdauer',                             'Number',    'technische_daten', null,              'zeit', 'h',      'Nein',       null,     'Nein',       'Optional','Nein', 'Ja',       'Nein',      null,   'PIM',  null],
                ['leistungsaufnahme',   'Leistungsaufnahme',   'Power Consumption',  'Maximale elektrische Leistungsaufnahme',        'Number',    'technische_daten', null,              'leistung','W',   'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],

                // Abmessungen
                ['gewicht',             'Gewicht',             'Weight',             'Produktgewicht',                                'Float',     'abmessungen',     null,              'gewicht','g',    'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],
                ['breite',              'Breite',              'Width',              'Produktbreite',                                 'Float',     'abmessungen',     null,              'laenge','mm',    'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],
                ['hoehe',               'Höhe',                'Height',             'Produkthöhe',                                   'Float',     'abmessungen',     null,              'laenge','mm',    'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],
                ['tiefe',               'Tiefe',               'Depth',              'Produkttiefe',                                  'Float',     'abmessungen',     null,              'laenge','mm',    'Nein',       null,     'Nein',       'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],

                // Marketing
                ['marketing_text',      'Marketing-Text',      'Marketing Copy',     'Werblicher Langtext',                           'String',    'marketing',        null,              null,   null,     'Nein',       null,     'Ja',         'Optional','Nein', 'Nein',     'Ja',        null,   'PIM',  null],
                ['usp',                 'USP',                 'USP',                'Alleinstellungsmerkmal',                        'String',    'marketing',        null,              null,   null,     'Ja',         3,        'Ja',         'Optional','Nein', 'Nein',     'Nein',      null,   'PIM',  null],
            ],

            // ── 06 Hierarchien ──────────────────────────────────────────
            '06_Hierarchien' => [
                // Master-Hierarchie: Produktkatalog
                ['Produktkatalog', 'master', 'Elektronik',     null,            null,          null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Audio',         null,          null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Audio',         'Kopfhörer',   null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Audio',         'Lautsprecher', null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Computer',      null,          null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Computer',      'Laptops',     null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Computer',      'Desktops',    null, null, null],
                ['Produktkatalog', 'master', 'Elektronik',     'Computer',      'Peripherie',  null, null, null],
                ['Produktkatalog', 'master', 'Haushalt',       null,            null,          null, null, null],
                ['Produktkatalog', 'master', 'Haushalt',       'Küchengeräte',  null,          null, null, null],
                ['Produktkatalog', 'master', 'Software',       null,            null,          null, null, null],
                ['Produktkatalog', 'master', 'Software',       'Büroanwendungen', null,        null, null, null],

                // Output-Hierarchie: Webshop
                ['Webshop',        'output', 'Neuheiten',      null,            null,          null, null, null],
                ['Webshop',        'output', 'Bestseller',     null,            null,          null, null, null],
                ['Webshop',        'output', 'Sale',           null,            null,          null, null, null],
                ['Webshop',        'output', 'Geschenkideen',  null,            null,          null, null, null],
            ],

            // ── 07 Hierarchie-Attribute ─────────────────────────────────
            '07_Hierarchie_Attribute' => [
                // Kopfhörer-Kategorie
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'beschreibung',        null,                 null, 1,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'farbe',               null,                 null, 2,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'material',            null,                 null, 3,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'bluetooth_version',   null,                 null, 4,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'gewicht',             null,                 null, 5,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'akkulaufzeit',        null,                 null, 6,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Kopfhörer/',     'anschluss',           null,                 null, 7,  'Nein'],

                // Lautsprecher-Kategorie
                ['Produktkatalog', '/Elektronik/Audio/Lautsprecher/',  'beschreibung',        null,                 null, 1,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Lautsprecher/',  'farbe',               null,                 null, 2,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Lautsprecher/',  'leistungsaufnahme',   null,                 null, 3,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Lautsprecher/',  'gewicht',             null,                 null, 4,  'Nein'],
                ['Produktkatalog', '/Elektronik/Audio/Lautsprecher/',  'bluetooth_version',   null,                 null, 5,  'Nein'],

                // Laptops-Kategorie
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'beschreibung',        null,                 null, 1,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'farbe',               null,                 null, 2,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'betriebssystem',      null,                 null, 3,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'speicherkapazitaet',  null,                 null, 4,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'akkulaufzeit',        null,                 null, 5,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'gewicht',             null,                 null, 6,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'breite',              'Abmessungen',        1,    7,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'hoehe',               'Abmessungen',        1,    8,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Laptops/',    'tiefe',               'Abmessungen',        1,    9,  'Nein'],

                // Peripherie-Kategorie
                ['Produktkatalog', '/Elektronik/Computer/Peripherie/', 'beschreibung',        null,                 null, 1,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Peripherie/', 'farbe',               null,                 null, 2,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Peripherie/', 'anschluss',           null,                 null, 3,  'Nein'],
                ['Produktkatalog', '/Elektronik/Computer/Peripherie/', 'gewicht',             null,                 null, 4,  'Nein'],

                // Software-Kategorie
                ['Produktkatalog', '/Software/Büroanwendungen/',       'beschreibung',        null,                 null, 1,  'Nein'],
                ['Produktkatalog', '/Software/Büroanwendungen/',       'betriebssystem',      null,                 null, 2,  'Nein'],
            ],

            // ── 08 Produkte ─────────────────────────────────────────────
            '08_Produkte' => [
                ['ELEC-KH-001', 'Bluetooth Kopfhörer Pro',          'Bluetooth Headphones Pro',       'elektronik', '4012345000011', 'active'],
                ['ELEC-KH-002', 'In-Ear Kopfhörer Sport',           'In-Ear Sport Headphones',        'elektronik', '4012345000028', 'active'],
                ['ELEC-LS-001', 'Smart Speaker Mini',                'Smart Speaker Mini',             'elektronik', '4012345000035', 'active'],
                ['ELEC-LS-002', 'Soundbar Pro 5.1',                  'Soundbar Pro 5.1',               'elektronik', '4012345000042', 'draft'],
                ['ELEC-LP-001', 'UltraBook Pro 15',                  'UltraBook Pro 15',               'elektronik', '4012345000059', 'active'],
                ['ELEC-LP-002', 'Business Laptop 14',                'Business Laptop 14',             'elektronik', '4012345000066', 'active'],
                ['ZUB-KB-001',  'Mechanische Tastatur RGB',          'Mechanical Keyboard RGB',        'zubehoer',   '4012345000073', 'active'],
                ['ZUB-MS-001',  'Ergonomische Maus Wireless',        'Ergonomic Wireless Mouse',       'zubehoer',   '4012345000080', 'active'],
                ['ZUB-WC-001',  'HD Webcam 1080p',                   'HD Webcam 1080p',                'zubehoer',   '4012345000097', 'active'],
                ['SW-OF-001',   'Office Suite Pro',                  'Office Suite Pro',               'software',   null,             'active'],
                ['SW-OF-002',   'Bildbearbeitung Premium',           'Image Editor Premium',           'software',   null,             'draft'],
            ],

            // ── 09 Produktwerte ─────────────────────────────────────────
            '09_Produktwerte' => [
                // Bluetooth Kopfhörer Pro (ELEC-KH-001)
                ['ELEC-KH-001', 'beschreibung',        'Premium Over-Ear Kopfhörer mit Active Noise Cancelling und 30 Stunden Akkulaufzeit.',                        null,  'de', null],
                ['ELEC-KH-001', 'beschreibung',        'Premium over-ear headphones with active noise cancelling and 30 hours battery life.',                         null,  'en', null],
                ['ELEC-KH-001', 'kurzbeschreibung',    'ANC-Kopfhörer mit langer Akkulaufzeit',                                                                       null,  'de', null],
                ['ELEC-KH-001', 'farbe',               'schwarz',                    null,  null, null],
                ['ELEC-KH-001', 'material',            'kunststoff',                 null,  null, null],
                ['ELEC-KH-001', 'bluetooth_version',   '5.3',                        null,  null, null],
                ['ELEC-KH-001', 'gewicht',             250,                          'g',   null, null],
                ['ELEC-KH-001', 'akkulaufzeit',        30,                           'h',   null, null],
                ['ELEC-KH-001', 'anschluss',           'usb_c',                      null,  null, 0],
                ['ELEC-KH-001', 'anschluss',           'klinke_35',                  null,  null, 1],
                ['ELEC-KH-001', 'anschluss',           'bluetooth',                  null,  null, 2],
                ['ELEC-KH-001', 'breite',              180,                          'mm',  null, null],
                ['ELEC-KH-001', 'hoehe',               200,                          'mm',  null, null],
                ['ELEC-KH-001', 'tiefe',               80,                           'mm',  null, null],
                ['ELEC-KH-001', 'garantie_jahre',      2,                            null,  null, null],
                ['ELEC-KH-001', 'herstellerland',      'Deutschland',                null,  'de', null],
                ['ELEC-KH-001', 'herstellerland',      'Germany',                    null,  'en', null],
                ['ELEC-KH-001', 'marketing_text',      'Tauchen Sie ein in Ihre Musik – mit erstklassigem Noise Cancelling.',                                         null,  'de', null],
                ['ELEC-KH-001', 'marketing_text',      'Immerse yourself in your music – with premium noise cancelling.',                                              null,  'en', null],
                ['ELEC-KH-001', 'usp',                 'Active Noise Cancelling',    null,  'de', 0],
                ['ELEC-KH-001', 'usp',                 '30h Akkulaufzeit',           null,  'de', 1],
                ['ELEC-KH-001', 'usp',                 'Bluetooth 5.3',              null,  'de', 2],

                // In-Ear Kopfhörer Sport (ELEC-KH-002)
                ['ELEC-KH-002', 'beschreibung',        'Sportliche In-Ear Kopfhörer mit IP67-Schutz, perfekt für Training und Outdoor.',                              null,  'de', null],
                ['ELEC-KH-002', 'beschreibung',        'Sporty in-ear headphones with IP67 rating, perfect for workout and outdoor activities.',                       null,  'en', null],
                ['ELEC-KH-002', 'farbe',               'schwarz',                    null,  null, null],
                ['ELEC-KH-002', 'material',            'kunststoff',                 null,  null, null],
                ['ELEC-KH-002', 'bluetooth_version',   '5.2',                        null,  null, null],
                ['ELEC-KH-002', 'gewicht',             6.5,                          'g',   null, null],
                ['ELEC-KH-002', 'akkulaufzeit',        8,                            'h',   null, null],
                ['ELEC-KH-002', 'anschluss',           'bluetooth',                  null,  null, 0],
                ['ELEC-KH-002', 'garantie_jahre',      1,                            null,  null, null],

                // Smart Speaker Mini (ELEC-LS-001)
                ['ELEC-LS-001', 'beschreibung',        'Kompakter Smart Speaker mit Sprachassistent und 360°-Sound.',                                                 null,  'de', null],
                ['ELEC-LS-001', 'beschreibung',        'Compact smart speaker with voice assistant and 360° sound.',                                                   null,  'en', null],
                ['ELEC-LS-001', 'farbe',               'weiss',                      null,  null, null],
                ['ELEC-LS-001', 'material',            'kunststoff',                 null,  null, null],
                ['ELEC-LS-001', 'bluetooth_version',   '5.0',                        null,  null, null],
                ['ELEC-LS-001', 'gewicht',             320,                          'g',   null, null],
                ['ELEC-LS-001', 'leistungsaufnahme',   15,                           'W',   null, null],
                ['ELEC-LS-001', 'anschluss',           'bluetooth',                  null,  null, 0],
                ['ELEC-LS-001', 'anschluss',           'klinke_35',                  null,  null, 1],
                ['ELEC-LS-001', 'garantie_jahre',      2,                            null,  null, null],

                // Soundbar Pro 5.1 (ELEC-LS-002)
                ['ELEC-LS-002', 'beschreibung',        'Premium Soundbar mit kabellosem Subwoofer und Dolby Atmos Unterstützung.',                                     null,  'de', null],
                ['ELEC-LS-002', 'farbe',               'schwarz',                    null,  null, null],
                ['ELEC-LS-002', 'material',            'aluminium',                  null,  null, null],
                ['ELEC-LS-002', 'gewicht',             4200,                         'g',   null, null],
                ['ELEC-LS-002', 'leistungsaufnahme',   300,                          'W',   null, null],
                ['ELEC-LS-002', 'anschluss',           'hdmi',                       null,  null, 0],
                ['ELEC-LS-002', 'anschluss',           'bluetooth',                  null,  null, 1],
                ['ELEC-LS-002', 'breite',              1100,                         'mm',  null, null],
                ['ELEC-LS-002', 'hoehe',               65,                           'mm',  null, null],
                ['ELEC-LS-002', 'tiefe',               110,                          'mm',  null, null],

                // UltraBook Pro 15 (ELEC-LP-001)
                ['ELEC-LP-001', 'beschreibung',        'Leistungsstarkes 15-Zoll Ultrabook mit OLED-Display und ganztägiger Akkulaufzeit.',                            null,  'de', null],
                ['ELEC-LP-001', 'beschreibung',        'Powerful 15-inch ultrabook with OLED display and all-day battery life.',                                       null,  'en', null],
                ['ELEC-LP-001', 'farbe',               'silber',                     null,  null, null],
                ['ELEC-LP-001', 'material',            'aluminium',                  null,  null, null],
                ['ELEC-LP-001', 'betriebssystem',      'windows',                    null,  null, null],
                ['ELEC-LP-001', 'speicherkapazitaet',  512,                          'GB',  null, null],
                ['ELEC-LP-001', 'akkulaufzeit',        12,                           'h',   null, null],
                ['ELEC-LP-001', 'gewicht',             1400,                         'g',   null, null],
                ['ELEC-LP-001', 'breite',              355,                          'mm',  null, null],
                ['ELEC-LP-001', 'hoehe',               16,                           'mm',  null, null],
                ['ELEC-LP-001', 'tiefe',               240,                          'mm',  null, null],
                ['ELEC-LP-001', 'anschluss',           'usb_c',                      null,  null, 0],
                ['ELEC-LP-001', 'anschluss',           'usb_a',                      null,  null, 1],
                ['ELEC-LP-001', 'anschluss',           'hdmi',                       null,  null, 2],
                ['ELEC-LP-001', 'energieeffizienzklasse','a_plus',                   null,  null, null],
                ['ELEC-LP-001', 'garantie_jahre',      3,                            null,  null, null],
                ['ELEC-LP-001', 'usp',                 'OLED-Display',               null,  'de', 0],
                ['ELEC-LP-001', 'usp',                 '12h Akkulaufzeit',           null,  'de', 1],
                ['ELEC-LP-001', 'usp',                 'Nur 1,4 kg',                 null,  'de', 2],

                // Business Laptop 14 (ELEC-LP-002)
                ['ELEC-LP-002', 'beschreibung',        'Robustes Business-Laptop mit Sicherheitsfeatures und Docking-Station-Support.',                                null,  'de', null],
                ['ELEC-LP-002', 'farbe',               'schwarz',                    null,  null, null],
                ['ELEC-LP-002', 'material',            'kunststoff',                 null,  null, null],
                ['ELEC-LP-002', 'betriebssystem',      'windows',                    null,  null, null],
                ['ELEC-LP-002', 'speicherkapazitaet',  256,                          'GB',  null, null],
                ['ELEC-LP-002', 'akkulaufzeit',        10,                           'h',   null, null],
                ['ELEC-LP-002', 'gewicht',             1650,                         'g',   null, null],
                ['ELEC-LP-002', 'energieeffizienzklasse','a',                        null,  null, null],
                ['ELEC-LP-002', 'garantie_jahre',      3,                            null,  null, null],

                // Mechanische Tastatur RGB (ZUB-KB-001)
                ['ZUB-KB-001',  'beschreibung',        'Mechanische Gaming-Tastatur mit RGB-Beleuchtung und Cherry MX Switches.',                                      null,  'de', null],
                ['ZUB-KB-001',  'beschreibung',        'Mechanical gaming keyboard with RGB lighting and Cherry MX switches.',                                         null,  'en', null],
                ['ZUB-KB-001',  'farbe',               'schwarz',                    null,  null, null],
                ['ZUB-KB-001',  'material',            'aluminium',                  null,  null, null],
                ['ZUB-KB-001',  'anschluss',           'usb_c',                      null,  null, 0],
                ['ZUB-KB-001',  'anschluss',           'usb_a',                      null,  null, 1],
                ['ZUB-KB-001',  'gewicht',             850,                          'g',   null, null],
                ['ZUB-KB-001',  'garantie_jahre',      2,                            null,  null, null],

                // Ergonomische Maus (ZUB-MS-001)
                ['ZUB-MS-001',  'beschreibung',        'Ergonomische Funkmaus mit vertikalem Design für ermüdungsfreies Arbeiten.',                                    null,  'de', null],
                ['ZUB-MS-001',  'farbe',               'silber',                     null,  null, null],
                ['ZUB-MS-001',  'material',            'kunststoff',                 null,  null, null],
                ['ZUB-MS-001',  'anschluss',           'bluetooth',                  null,  null, 0],
                ['ZUB-MS-001',  'anschluss',           'usb_a',                      null,  null, 1],
                ['ZUB-MS-001',  'gewicht',             95,                           'g',   null, null],
                ['ZUB-MS-001',  'akkulaufzeit',        70,                           'h',   null, null],

                // HD Webcam (ZUB-WC-001)
                ['ZUB-WC-001',  'beschreibung',        'Full-HD Webcam mit Autofokus und integriertem Mikrofon für Videokonferenzen.',                                 null,  'de', null],
                ['ZUB-WC-001',  'farbe',               'schwarz',                    null,  null, null],
                ['ZUB-WC-001',  'anschluss',           'usb_a',                      null,  null, 0],
                ['ZUB-WC-001',  'gewicht',             120,                          'g',   null, null],

                // Office Suite Pro (SW-OF-001)
                ['SW-OF-001',   'beschreibung',        'Vollständige Office-Suite mit Textverarbeitung, Tabellenkalkulation und Präsentationen.',                       null,  'de', null],
                ['SW-OF-001',   'beschreibung',        'Complete office suite with word processor, spreadsheet and presentation tools.',                                null,  'en', null],
                ['SW-OF-001',   'betriebssystem',      'windows',                    null,  null, null],
                ['SW-OF-001',   'garantie_jahre',      1,                            null,  null, null],

                // Bildbearbeitung Premium (SW-OF-002)
                ['SW-OF-002',   'beschreibung',        'Professionelle Bildbearbeitung mit KI-gestützter Retusche und RAW-Entwicklung.',                               null,  'de', null],
                ['SW-OF-002',   'betriebssystem',      'windows',                    null,  null, null],
            ],

            // ── 10 Varianten ────────────────────────────────────────────
            '10_Varianten' => [
                // Kopfhörer-Varianten (Farben)
                ['ELEC-KH-001', 'ELEC-KH-001-BLK', 'Bluetooth Kopfhörer Pro - Schwarz',  'BT Headphones Pro - Black',  '4012345001011', 'active'],
                ['ELEC-KH-001', 'ELEC-KH-001-WHT', 'Bluetooth Kopfhörer Pro - Weiß',     'BT Headphones Pro - White',  '4012345001012', 'active'],
                ['ELEC-KH-001', 'ELEC-KH-001-SLV', 'Bluetooth Kopfhörer Pro - Silber',    'BT Headphones Pro - Silver', '4012345001013', 'active'],

                // Laptop-Varianten (Speicher)
                ['ELEC-LP-001', 'ELEC-LP-001-256',  'UltraBook Pro 15 - 256GB SSD',       'UltraBook Pro 15 - 256GB',   '4012345002011', 'active'],
                ['ELEC-LP-001', 'ELEC-LP-001-512',  'UltraBook Pro 15 - 512GB SSD',       'UltraBook Pro 15 - 512GB',   '4012345002012', 'active'],
                ['ELEC-LP-001', 'ELEC-LP-001-1TB',  'UltraBook Pro 15 - 1TB SSD',         'UltraBook Pro 15 - 1TB',     '4012345002013', 'active'],
            ],

            // ── 11 Produkt_Hierarchien ──────────────────────────────────
            '11_Produkt_Hierarchien' => [
                // Master-Hierarchie-Zuordnungen
                ['ELEC-KH-001', 'Produktkatalog', '/Elektronik/Audio/Kopfhörer/'],
                ['ELEC-KH-002', 'Produktkatalog', '/Elektronik/Audio/Kopfhörer/'],
                ['ELEC-LS-001', 'Produktkatalog', '/Elektronik/Audio/Lautsprecher/'],
                ['ELEC-LS-002', 'Produktkatalog', '/Elektronik/Audio/Lautsprecher/'],
                ['ELEC-LP-001', 'Produktkatalog', '/Elektronik/Computer/Laptops/'],
                ['ELEC-LP-002', 'Produktkatalog', '/Elektronik/Computer/Laptops/'],
                ['ZUB-KB-001',  'Produktkatalog', '/Elektronik/Computer/Peripherie/'],
                ['ZUB-MS-001',  'Produktkatalog', '/Elektronik/Computer/Peripherie/'],
                ['ZUB-WC-001',  'Produktkatalog', '/Elektronik/Computer/Peripherie/'],
                ['SW-OF-001',   'Produktkatalog', '/Software/Büroanwendungen/'],
                ['SW-OF-002',   'Produktkatalog', '/Software/Büroanwendungen/'],

                // Output-Hierarchie-Zuordnungen (Webshop)
                ['ELEC-KH-001', 'Webshop', '/Bestseller/'],
                ['ELEC-LS-001', 'Webshop', '/Bestseller/'],
                ['ELEC-LP-001', 'Webshop', '/Neuheiten/'],
                ['ELEC-LS-002', 'Webshop', '/Neuheiten/'],
                ['ZUB-KB-001',  'Webshop', '/Bestseller/'],
                ['ZUB-MS-001',  'Webshop', '/Geschenkideen/'],
                ['SW-OF-001',   'Webshop', '/Bestseller/'],
            ],

            // ── 12 Produktbeziehungen ───────────────────────────────────
            '12_Produktbeziehungen' => [
                // Cross-Sell: Kopfhörer ↔ Lautsprecher
                ['ELEC-KH-001', 'ELEC-KH-002', 'cross_sell', 1],
                ['ELEC-KH-001', 'ELEC-LS-001', 'cross_sell', 2],

                // Zubehör: Laptops → Peripherie
                ['ELEC-LP-001', 'ZUB-KB-001',  'zubehoer',   1],
                ['ELEC-LP-001', 'ZUB-MS-001',  'zubehoer',   2],
                ['ELEC-LP-001', 'ZUB-WC-001',  'zubehoer',   3],
                ['ELEC-LP-002', 'ZUB-KB-001',  'zubehoer',   1],
                ['ELEC-LP-002', 'ZUB-MS-001',  'zubehoer',   2],

                // Up-Sell: günstigeres → teureres Modell
                ['ELEC-LP-002', 'ELEC-LP-001', 'up_sell',    1],
                ['ELEC-KH-002', 'ELEC-KH-001', 'up_sell',    1],

                // Software-Bundle
                ['SW-OF-001',   'SW-OF-002',   'cross_sell', 1],
            ],

            // ── 13 Preise ───────────────────────────────────────────────
            '13_Preise' => [
                // Listenpreise (EUR)
                ['ELEC-KH-001', 'listenpreis',  249.99,  'EUR', '2025-01-01', null,         'DE', null, null],
                ['ELEC-KH-002', 'listenpreis',  79.99,   'EUR', '2025-01-01', null,         'DE', null, null],
                ['ELEC-LS-001', 'listenpreis',  59.99,   'EUR', '2025-01-01', null,         'DE', null, null],
                ['ELEC-LS-002', 'listenpreis',  399.99,  'EUR', '2025-01-01', null,         'DE', null, null],
                ['ELEC-LP-001', 'listenpreis',  1299.00, 'EUR', '2025-01-01', null,         'DE', null, null],
                ['ELEC-LP-002', 'listenpreis',  899.00,  'EUR', '2025-01-01', null,         'DE', null, null],
                ['ZUB-KB-001',  'listenpreis',  129.99,  'EUR', '2025-01-01', null,         'DE', null, null],
                ['ZUB-MS-001',  'listenpreis',  69.99,   'EUR', '2025-01-01', null,         'DE', null, null],
                ['ZUB-WC-001',  'listenpreis',  49.99,   'EUR', '2025-01-01', null,         'DE', null, null],
                ['SW-OF-001',   'listenpreis',  149.00,  'EUR', '2025-01-01', null,         'DE', null, null],
                ['SW-OF-002',   'listenpreis',  89.00,   'EUR', '2025-01-01', null,         'DE', null, null],

                // Aktionspreise (zeitlich begrenzt)
                ['ELEC-KH-001', 'aktionspreis', 199.99,  'EUR', '2025-03-01', '2025-03-31', 'DE', null, null],
                ['ELEC-LP-001', 'aktionspreis', 1099.00, 'EUR', '2025-03-01', '2025-03-31', 'DE', null, null],
                ['ZUB-KB-001',  'aktionspreis', 99.99,   'EUR', '2025-03-01', '2025-03-31', 'DE', null, null],

                // Staffelpreise (Tastatur)
                ['ZUB-KB-001',  'staffelpreis', 119.99,  'EUR', '2025-01-01', null,         'DE', 5,    9],
                ['ZUB-KB-001',  'staffelpreis', 109.99,  'EUR', '2025-01-01', null,         'DE', 10,   49],
                ['ZUB-KB-001',  'staffelpreis', 99.99,   'EUR', '2025-01-01', null,         'DE', 50,   null],

                // USD-Preise (ausgewählte Produkte)
                ['ELEC-KH-001', 'listenpreis',  279.99,  'USD', '2025-01-01', null,         'US', null, null],
                ['ELEC-LP-001', 'listenpreis',  1399.00, 'USD', '2025-01-01', null,         'US', null, null],
            ],

            // ── 14 Medien ───────────────────────────────────────────────
            '14_Medien' => [
                // Kopfhörer
                ['ELEC-KH-001', 'kopfhoerer_pro_front.jpg',    'image',    'teaser',   'Kopfhörer Pro - Frontansicht',      'Headphones Pro - Front',     'Bluetooth Kopfhörer Pro Frontansicht', 1, 'Ja'],
                ['ELEC-KH-001', 'kopfhoerer_pro_seite.jpg',    'image',    'gallery',  'Kopfhörer Pro - Seitenansicht',     'Headphones Pro - Side',      'Bluetooth Kopfhörer Pro Seitenansicht', 2, 'Nein'],
                ['ELEC-KH-001', 'kopfhoerer_pro_spec.pdf',     'document', 'document', 'Datenblatt Kopfhörer Pro',          'Datasheet Headphones Pro',   null, 3, 'Nein'],

                // Smart Speaker
                ['ELEC-LS-001', 'speaker_mini_front.jpg',      'image',    'teaser',   'Smart Speaker Mini - Vorderseite',  'Smart Speaker Mini - Front', 'Smart Speaker Mini Produktbild', 1, 'Ja'],

                // Laptops
                ['ELEC-LP-001', 'ultrabook_pro_open.jpg',      'image',    'teaser',   'UltraBook Pro 15 - Offen',          'UltraBook Pro 15 - Open',    'UltraBook Pro aufgeklappt', 1, 'Ja'],
                ['ELEC-LP-001', 'ultrabook_pro_closed.jpg',    'image',    'gallery',  'UltraBook Pro 15 - Geschlossen',    'UltraBook Pro 15 - Closed',  'UltraBook Pro zugeklappt', 2, 'Nein'],
                ['ELEC-LP-001', 'ultrabook_pro_spec.pdf',      'document', 'document', 'Datenblatt UltraBook Pro',          'Datasheet UltraBook Pro',    null, 3, 'Nein'],

                // Tastatur
                ['ZUB-KB-001',  'tastatur_rgb_top.jpg',        'image',    'teaser',   'Tastatur RGB - Draufsicht',         'Keyboard RGB - Top',         'Mechanische Tastatur RGB Draufsicht', 1, 'Ja'],
                ['ZUB-KB-001',  'tastatur_rgb_demo.mp4',       'video',    'gallery',  'Tastatur RGB - Demo Video',         'Keyboard RGB - Demo',        null, 2, 'Nein'],

                // Maus
                ['ZUB-MS-001',  'maus_ergonomisch.jpg',        'image',    'teaser',   'Ergonomische Maus',                 'Ergonomic Mouse',            'Ergonomische Funkmaus Produktbild', 1, 'Ja'],
            ],
        ];
    }

    /**
     * Erzeugt das Demo-Import-Template.
     */
    public function generate(string $outputPath): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $demoData = $this->getDemoData();
        $sheetIndex = 0;

        foreach (self::SHEET_HEADERS as $sheetName => $headers) {
            $worksheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($worksheet, $sheetIndex);
            $sheetIndex++;

            $this->writeHeaders($worksheet, $headers);
            $this->styleHeaders($worksheet, $headers);

            if (isset($demoData[$sheetName])) {
                $this->writeData($worksheet, $headers, $demoData[$sheetName]);
                $this->styleDataRows($worksheet, $headers, count($demoData[$sheetName]));
            }

            $this->autoSizeColumns($worksheet, $headers);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }

    private function writeHeaders(Worksheet $worksheet, array $headers): void
    {
        foreach ($headers as $column => $headerText) {
            $worksheet->setCellValue($column . '1', $headerText);
        }
    }

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

        foreach ($headers as $column => $headerText) {
            if (str_contains($headerText, '*')) {
                $worksheet->getStyle($column . '1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD4A017');
            }
        }
    }

    /**
     * Schreibt Demodaten ab Zeile 2.
     */
    private function writeData(Worksheet $worksheet, array $headers, array $rows): void
    {
        $columns = array_keys($headers);

        foreach ($rows as $rowIndex => $rowData) {
            $excelRow = $rowIndex + 2; // Zeile 2 aufwärts

            foreach ($rowData as $colIndex => $value) {
                if ($colIndex >= count($columns)) {
                    break;
                }

                $column = $columns[$colIndex];

                if ($value !== null) {
                    $worksheet->setCellValue($column . $excelRow, $value);
                }
            }
        }
    }

    /**
     * Formatiert Datenzeilen mit Zebra-Streifen.
     */
    private function styleDataRows(Worksheet $worksheet, array $headers, int $rowCount): void
    {
        $lastColumn = array_key_last($headers);

        for ($i = 0; $i < $rowCount; $i++) {
            $excelRow = $i + 2;
            $range = 'A' . $excelRow . ':' . $lastColumn . $excelRow;

            $worksheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFD9D9D9'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ]);

            // Zebra-Streifen
            if ($i % 2 === 1) {
                $worksheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF2F2F2');
            }
        }
    }

    private function autoSizeColumns(Worksheet $worksheet, array $headers): void
    {
        foreach (array_keys($headers) as $column) {
            $worksheet->getColumnDimension($column)->setWidth(22);
        }

        $worksheet->getColumnDimension('A')->setWidth(28);

        // Breitere Spalten für Beschreibungs-/Wertfelder
        if ($worksheet->getTitle() === '09_Produktwerte') {
            $worksheet->getColumnDimension('C')->setWidth(50);
        }
        if ($worksheet->getTitle() === '14_Medien') {
            $worksheet->getColumnDimension('B')->setWidth(32);
            $worksheet->getColumnDimension('E')->setWidth(35);
        }
    }
}
