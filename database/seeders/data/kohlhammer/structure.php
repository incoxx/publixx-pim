<?php

declare(strict_types=1);

/*
 * Kohlhammer/COVER — Strukturmanifest.
 *
 * Alles, was neben den Attributen für eine leere anyPIM-Instanz nötig ist:
 * Attributgruppen, Produkttypen, Beziehungstypen, Preisarten,
 * Preis-Metadaten, Wertelisten und Hierarchien.
 *
 * Abgeleitet aus den PIMCORE-Importskripten. Wo eine Angabe nicht aus dem
 * Code ableitbar ist, sondern aus den COVER-Daten kommt, steht es als
 * Kommentar dabei — diese Stellen füllt der Profiling-Lauf (Schritt 2).
 */
return [

    // ─── Attributgruppen ────────────────────────────────────────────────
    'attribute_types' => [
        ['technical_name' => 'identifikation',  'name_de' => 'Identifikation',        'sort_order' => 10],
        ['technical_name' => 'titel',           'name_de' => 'Titel',                 'sort_order' => 20],
        ['technical_name' => 'ausgabe',         'name_de' => 'Ausgabe',               'sort_order' => 30],
        ['technical_name' => 'sprache',         'name_de' => 'Sprache',               'sort_order' => 40],
        ['technical_name' => 'umfang',          'name_de' => 'Umfang',                'sort_order' => 50],
        ['technical_name' => 'form',            'name_de' => 'Produktform',           'sort_order' => 60],
        ['technical_name' => 'masse',           'name_de' => 'Maße und Gewicht',      'sort_order' => 70],
        ['technical_name' => 'verlag',          'name_de' => 'Verlag',                'sort_order' => 80],
        ['technical_name' => 'status-termine',  'name_de' => 'Status und Termine',    'sort_order' => 90],
        ['technical_name' => 'handel',          'name_de' => 'Handel',                'sort_order' => 100],
        ['technical_name' => 'zeitschrift',     'name_de' => 'Zeitschrift',           'sort_order' => 110],
        ['technical_name' => 'reihe',           'name_de' => 'Reihe',                 'sort_order' => 120],
        ['technical_name' => 'klassifikation',  'name_de' => 'Klassifikation',        'sort_order' => 130],
        ['technical_name' => 'sichtbarkeit',    'name_de' => 'Sichtbarkeit',          'sort_order' => 140],
        ['technical_name' => 'texte',           'name_de' => 'Texte',                 'sort_order' => 150],
        ['technical_name' => 'subject',         'name_de' => 'Sachgruppen',           'sort_order' => 160],
        ['technical_name' => 'bearbeiter',      'name_de' => 'Bearbeiter / Lektorat', 'sort_order' => 170],
        ['technical_name' => 'adresse',         'name_de' => 'Adressdaten',           'sort_order' => 180],
        ['technical_name' => 'contributor',     'name_de' => 'Mitwirkende',           'sort_order' => 190],
        ['technical_name' => 'beziehung',       'name_de' => 'Beziehungsattribute',   'sort_order' => 200],
    ],

    // ─── Produkttypen ───────────────────────────────────────────────────
    // Die Titel-Produkttypen entsprechen SF_PRODUCT.PRODUKTTYP. Hier sind
    // nur die aus den Skripten belegbaren Codes gesetzt (ZSEH/ZSOP/ZEBU aus
    // import_zeitschriften_struktur.php); alle weiteren ergänzt der
    // Profiling-Lauf per SELECT DISTINCT PRODUKTTYP.
    'product_types' => [
        [
            'technical_name' => 'titel', 'name_de' => 'Titel (allgemein)',
            'has_prices' => true, 'has_media' => true, 'has_ean' => true,
            'has_physical_dimensions' => true, 'is_master_data' => false, 'sort_order' => 10,
            'default_attribute_groups' => ['identifikation', 'titel', 'ausgabe', 'sprache', 'umfang', 'form', 'masse', 'verlag', 'status-termine', 'handel', 'reihe', 'klassifikation', 'sichtbarkeit', 'texte', 'subject', 'bearbeiter'],
            'cover_produkttyp' => null,   // Auffangtyp für unbekannte PRODUKTTYP-Codes
        ],
        [
            'technical_name' => 'zeitschrift-heft', 'name_de' => 'Zeitschriftenheft',
            'has_prices' => true, 'has_media' => true, 'has_ean' => true,
            'has_physical_dimensions' => true, 'is_master_data' => false, 'sort_order' => 20,
            'default_attribute_groups' => ['identifikation', 'titel', 'ausgabe', 'sprache', 'umfang', 'form', 'masse', 'verlag', 'status-termine', 'handel', 'zeitschrift', 'reihe', 'klassifikation', 'sichtbarkeit', 'texte', 'subject', 'bearbeiter'],
            'cover_produkttyp' => 'ZSEH',
        ],
        [
            'technical_name' => 'zeitschrift-online', 'name_de' => 'Zeitschrift Online',
            'has_prices' => true, 'has_media' => true, 'is_master_data' => false, 'sort_order' => 30,
            'default_attribute_groups' => ['identifikation', 'titel', 'ausgabe', 'sprache', 'umfang', 'form', 'masse', 'verlag', 'status-termine', 'handel', 'zeitschrift', 'reihe', 'klassifikation', 'sichtbarkeit', 'texte', 'subject', 'bearbeiter'],
            'cover_produkttyp' => 'ZSOP',
        ],
        [
            'technical_name' => 'zeitschrift-ebook', 'name_de' => 'Zeitschrift E-Book',
            'has_prices' => true, 'has_media' => true, 'is_master_data' => false, 'sort_order' => 40,
            'default_attribute_groups' => ['identifikation', 'titel', 'ausgabe', 'sprache', 'umfang', 'form', 'masse', 'verlag', 'status-termine', 'handel', 'zeitschrift', 'reihe', 'klassifikation', 'sichtbarkeit', 'texte', 'subject', 'bearbeiter'],
            'cover_produkttyp' => 'ZEBU',
        ],
        [
            'technical_name' => 'contributor', 'name_de' => 'Mitwirkender',
            'has_prices' => false, 'has_media' => true, 'has_ean' => false,
            'has_physical_dimensions' => false, 'is_master_data' => true, 'sort_order' => 900,
            'default_attribute_groups' => ['contributor'],
            'cover_produkttyp' => null,
        ],
        [
            'technical_name' => 'adresse', 'name_de' => 'Adresse',
            'has_prices' => false, 'has_media' => false, 'has_ean' => false,
            'has_physical_dimensions' => false, 'is_master_data' => true, 'sort_order' => 910,
            'default_attribute_groups' => ['adresse'],
            'cover_produkttyp' => null,
        ],
    ],

    // ─── Beziehungstypen ────────────────────────────────────────────────
    // Ersetzen die PIMCORE-Relationsfelder. Die Metadaten der Kanten werden
    // zu Beziehungsattributen (siehe attributes.php, Block 'beziehung').
    'relation_types' => [
        [
            'technical_name' => 'koh-contributor', 'name_de' => 'Mitwirkender',
            'is_bidirectional' => false, 'source' => 'SF_PRODUCTCONTRIBUTOR',
            'source_types' => ['titel', 'zeitschrift-heft', 'zeitschrift-online', 'zeitschrift-ebook'],
            'target_types' => ['contributor'],
            'attributes'   => ['koh-contributor-role', 'koh-contributor-sort'],
        ],
        [
            'technical_name' => 'koh-contributor-address', 'name_de' => 'Adresse des Mitwirkenden',
            'is_bidirectional' => false, 'source' => 'SF_PRODUCTCONTRIBUTOR.ADR_NR/PERSON_NR',
            'source_types' => ['contributor'], 'target_types' => ['adresse'], 'attributes' => [],
        ],
        [
            'technical_name' => 'koh-series', 'name_de' => 'Reihe',
            'is_bidirectional' => true, 'source' => 'SF_PRODUCTSERIES',
            'source_types' => ['titel'], 'target_types' => ['titel'],
            'attributes' => ['koh-series-number'],
        ],
        [
            'technical_name' => 'koh-set', 'name_de' => 'Set',
            'is_bidirectional' => true, 'source' => 'SF_PRODUCTSETS',
            'source_types' => ['titel'], 'target_types' => ['titel'],
            'attributes' => ['koh-set-number'],
        ],
        [
            'technical_name' => 'koh-related-product', 'name_de' => 'Verwandtes Produkt',
            'is_bidirectional' => false, 'source' => 'SF_PRODUCTRELATEDPRODUCT',
            'source_types' => ['titel'], 'target_types' => ['titel'],
            'attributes' => ['koh-relation-code', 'koh-related-product-form'],
        ],
        [
            'technical_name' => 'koh-bundle-item', 'name_de' => 'Bundle-Bestandteil',
            'is_bidirectional' => false, 'source' => 'SF_PRODUCTCONTAINEDITEM',
            'source_types' => ['titel'], 'target_types' => ['titel'],
            'attributes' => ['koh-bundle-ausleitung', 'koh-bundle-anteil'],
        ],
        [
            'technical_name' => 'koh-dienstleister', 'name_de' => 'Dienstleister',
            'is_bidirectional' => false, 'source' => 'SF_PRODUCTDIENSTLEISTER',
            'source_types' => ['titel'], 'target_types' => ['adresse'],
            'attributes' => ['koh-dienstleister-typ'],
        ],
    ],

    // ─── Preisarten ─────────────────────────────────────────────────────
    // Aus import_sf_price.php belegbar. Land und PRICESTATUS gehören in die
    // Preisart, weil der Unique-Index auf product_prices beides nicht führt
    // (siehe plan-preis-metadaten.md, Abschnitt 4.1).
    // Weitere PRICETYPECODE/PRICESTATUS-Kombinationen ergänzt das Profiling.
    'price_types' => [
        ['technical_name' => 'lp-de-02', 'name_de' => 'Ladenpreis Deutschland (gültig)',
         'cover' => ['PRICETYPECODE' => 'LP-Deutschland', 'PRICESTATUS' => '02']],
        ['technical_name' => 'lp-at-02', 'name_de' => 'Ladenpreis Österreich (gültig)',
         'cover' => ['PRICETYPECODE' => 'LP-Österreich', 'PRICESTATUS' => '02']],
        ['technical_name' => 'lp-ch-02', 'name_de' => 'Ladenpreis Schweiz (gültig)',
         'cover' => ['PRICETYPECODE' => 'LP-Schweiz', 'PRICESTATUS' => '02']],
        ['technical_name' => 'ca-de-01', 'name_de' => 'CA-Preis Deutschland (geplant)',
         'cover' => ['PRICETYPECODE' => 'CA-Deutschland', 'PRICESTATUS' => '01']],
    ],

    // ─── Preis-Metadaten ────────────────────────────────────────────────
    // Benötigt das Feature aus plan-preis-metadaten.md. Solange es nicht
    // umgesetzt ist, überspringt der Seeder diesen Block mit Hinweis.
    'price_metadata' => [
        ['technical_name' => 'discount_percent',     'name_de' => 'Rabatt %',             'value_type' => 'number', 'sort_order' => 10,  'source' => 'DISCOUNTPERCENT'],
        ['technical_name' => 'discount_group',       'name_de' => 'Rabattgruppe',         'value_type' => 'text',   'sort_order' => 20,  'source' => 'DISCOUNTGROUP'],
        ['technical_name' => 'batch_quantity',       'name_de' => 'Gebindemenge',         'value_type' => 'number', 'sort_order' => 30,  'source' => 'BATCHQUANTITY'],
        ['technical_name' => 'tax_rate_code_1',      'name_de' => 'Steuerschlüssel 1',    'value_type' => 'text',   'sort_order' => 40,  'source' => 'TAXRATECODE1'],
        ['technical_name' => 'tax_rate_percent_1',   'name_de' => 'Steuersatz 1 %',       'value_type' => 'number', 'sort_order' => 50,  'source' => 'TAXRATEPERCENT1'],
        ['technical_name' => 'tax_taxable_amount_1', 'name_de' => 'Steuerbasis 1',        'value_type' => 'number', 'sort_order' => 60,  'source' => 'TAXABLEAMOUNT1'],
        ['technical_name' => 'tax_amount_1',         'name_de' => 'Steuerbetrag 1',       'value_type' => 'number', 'sort_order' => 70,  'source' => 'TAXAMOUNT1'],
        ['technical_name' => 'tax_rate_code_2',      'name_de' => 'Steuerschlüssel 2',    'value_type' => 'text',   'sort_order' => 80,  'source' => 'TAXRATECODE2'],
        ['technical_name' => 'tax_rate_percent_2',   'name_de' => 'Steuersatz 2 %',       'value_type' => 'number', 'sort_order' => 90,  'source' => 'TAXRATEPERCENT2'],
        ['technical_name' => 'tax_taxable_amount_2', 'name_de' => 'Steuerbasis 2',        'value_type' => 'number', 'sort_order' => 100, 'source' => 'TAXABLEAMOUNT2'],
        ['technical_name' => 'tax_amount_2',         'name_de' => 'Steuerbetrag 2',       'value_type' => 'number', 'sort_order' => 110, 'source' => 'TAXAMOUNT2'],
    ],

    // ─── Wertelisten ────────────────────────────────────────────────────
    // Die Container werden hier angelegt; die Einträge liefert der
    // Profiling-Lauf (SELECT DISTINCT über die jeweilige COVER-Spalte).
    // Ausnahme: onix-contributor-role — die Codes sind in den PIMCORE-Views
    // hart ausgewertet (app/Resources/views/Metadata/inc_productxml.html.php)
    // und deshalb hier bereits belegt.
    'value_lists' => [
        ['technical_name' => 'onix-contributor-role', 'name_de' => 'Mitwirkendenrolle (ONIX-Liste 17)', 'entries' => [
            ['technical_name' => 'A01', 'display_value_de' => 'Autor',             'sort_order' => 10],
            ['technical_name' => 'B01', 'display_value_de' => 'Herausgeber',       'sort_order' => 20],
            ['technical_name' => 'B09', 'display_value_de' => 'Reihenherausgeber', 'sort_order' => 30],
            ['technical_name' => 'A32', 'display_value_de' => 'Beitragender',      'sort_order' => 40],
            ['technical_name' => 'B17', 'display_value_de' => 'Bearbeiter',        'sort_order' => 50],
            ['technical_name' => 'B99', 'display_value_de' => 'Sonstige Mitwirkung', 'sort_order' => 60],
        ]],
        ['technical_name' => 'onix-notification-type',            'name_de' => 'Meldungsart (ONIX-Liste 1)',        'entries' => []],
        ['technical_name' => 'onix-product-form',                 'name_de' => 'Produktform (ONIX-Liste 150)',      'entries' => []],
        ['technical_name' => 'onix-product-form-detail',          'name_de' => 'Produktform Detail (ONIX-Liste 175)', 'entries' => []],
        ['technical_name' => 'onix-language',                     'name_de' => 'Sprache (ONIX-Liste 74)',           'entries' => []],
        ['technical_name' => 'onix-publishing-status',            'name_de' => 'Publikationsstatus (ONIX-Liste 64)', 'entries' => []],
        ['technical_name' => 'onix-product-availability',         'name_de' => 'Verfügbarkeit (ONIX-Liste 65)',     'entries' => []],
        ['technical_name' => 'onix-country',                      'name_de' => 'Land (ISO 3166-1)',                 'entries' => []],
        ['technical_name' => 'onix-extent-type',                  'name_de' => 'Umfangsart (ONIX-Liste 23)',        'entries' => []],
        ['technical_name' => 'onix-extent-unit',                  'name_de' => 'Umfangseinheit (ONIX-Liste 24)',    'entries' => []],
        ['technical_name' => 'onix-text-type',                    'name_de' => 'Texttyp (ONIX-Liste 153)',          'entries' => []],
        ['technical_name' => 'onix-relation-code',                'name_de' => 'Beziehungscode (ONIX-Liste 51)',    'entries' => []],
        ['technical_name' => 'onix-product-classification-type',  'name_de' => 'Klassifikationsart (ONIX-Liste 9)', 'entries' => []],
        ['technical_name' => 'koh-quartal-monat',                 'name_de' => 'Quartal/Monat',                     'entries' => []],
        ['technical_name' => 'koh-adress-typ',                    'name_de' => 'Adresstyp',                         'entries' => []],
        ['technical_name' => 'koh-anrede',                        'name_de' => 'Anrede',                            'entries' => []],
        ['technical_name' => 'koh-namenstitel',                   'name_de' => 'Namenstitel',                       'entries' => []],
        ['technical_name' => 'koh-tel-art',                       'name_de' => 'Kontaktart',                        'entries' => []],
        ['technical_name' => 'koh-bearbeiter-rolle',              'name_de' => 'Bearbeiterrolle',                   'entries' => []],
        ['technical_name' => 'koh-dienstleister-typ',             'name_de' => 'Dienstleistertyp',                  'entries' => []],
        ['technical_name' => 'koh-keywords',                      'name_de' => 'Schlagworte',                       'entries' => [], 'max_depth' => 2],
    ],

    // ─── Hierarchien ────────────────────────────────────────────────────
    // Nur die Wurzeln. Die Knoten entstehen beim Import aus
    // SF_WK_KATEGORIEN bzw. aus der Zeitschriftenstruktur.
    'hierarchies' => [
        ['technical_name' => 'koh-master',        'name_de' => 'Kohlhammer Warengruppen (Master)', 'hierarchy_type' => 'master',
         'source' => 'SF_PRODUCT.PRODUKTTYP'],
        ['technical_name' => 'koh-wk',            'name_de' => 'Warenkategorien (WK)',            'hierarchy_type' => 'output',
         'source' => 'SF_WK_KATEGORIEN, EBENE = "3 PRODUKT", 3 Ebenen über KAT_2_ID'],
        ['technical_name' => 'koh-lizenz',        'name_de' => 'Lizenzkategorien (EN)',           'hierarchy_type' => 'output',
         'source' => 'SF_WK_KATEGORIEN, KAT_2_BEZ_ENGL, REFCODE-Präfix "EN-"'],
        ['technical_name' => 'koh-vorschau',      'name_de' => 'Vorschau',                        'hierarchy_type' => 'output',
         'source' => 'import_sf_categories_vorschau_tree.php'],
        ['technical_name' => 'koh-buchinfo',      'name_de' => 'Buchinfo',                        'hierarchy_type' => 'output',
         'source' => 'import_sf_categories_buchinfo_tree.php'],
        ['technical_name' => 'koh-zeitschriften', 'name_de' => 'Zeitschriften',                   'hierarchy_type' => 'output',
         'source' => 'import_zeitschriften_struktur.php — Ebenen ISSN / Jahrgang / Heft'],
    ],

    // ─── Kernfelder ─────────────────────────────────────────────────────
    // COVER-Spalten, die auf feste Produktspalten gehen statt auf Attribute.
    // Diese Karte nutzt der Cover-Connector direkt.
    'core_fields' => [
        'RECORDIDENTIFIER' => ['column' => 'sku',             'note' => 'Leitschlüssel, Präfix je Stammdatentyp (CTR-, ADR-)'],
        'PRODUCTEAN'       => ['column' => 'ean',             'note' => null],
        'DISTINCTIVETITLE' => ['column' => 'name',            'note' => 'zusätzlich als übersetzbares Attribut koh-distinctivetitle'],
        'PRODUKTTYP'       => ['column' => 'product_type_id', 'note' => 'über product_types.cover_produkttyp aufgelöst'],
        'PUBLISHINGSTATUS' => ['column' => 'status',          'note' => 'gemappt auf draft/active/inactive/discontinued, Originalcode bleibt im Attribut'],
    ],

    // ─── Bewusst nicht übernommen ───────────────────────────────────────
    'ignored' => [
        'AUTOR'          => 'Denormalisierung aus OTHERTEXT "AS" — in anyPIM über die contributor-Beziehung',
        'RUECKENTEXT'    => 'Denormalisierung aus OTHERTEXT "RT" — wird ein Textattribut',
        'INLIZENZ'       => 'Denormalisierung aus SUBJECT — wird ein Subject-Attribut',
        'LPPRICE'        => 'Denormalisierung aus SF_PRODUCTPRICE — über Preisart lp-de-02',
        'LPPRICECH'      => 'Denormalisierung aus SF_PRODUCTPRICE — über Preisart lp-ch-02',
        'LPPRICEAT'      => 'Denormalisierung aus SF_PRODUCTPRICE — über Preisart lp-at-02',
        'CAPRICE'        => 'Denormalisierung aus SF_PRODUCTPRICE — über Preisart ca-de-01',
        'DISCOUNTPERCENT'=> 'Kopffeld am Produkt — in anyPIM Preis-Metadatum an der Preiszeile',
        'REPLACESISBN'   => 'im PIMCORE-Import auskommentiert',
        'REPLACEDBYISBN' => 'im PIMCORE-Import auskommentiert',
        'BENUTZER_ID_LEKTOR' => 'im PIMCORE-Import auskommentiert — ersetzt durch Bearbeiter-Gruppe',
    ],
];
