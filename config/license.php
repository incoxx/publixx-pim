<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Public Key
    |--------------------------------------------------------------------------
    |
    | Ed25519 public key used to verify license signatures.
    | The private key is kept by the license issuer and never published.
    |
    */

    'public_key' => env('ANYPIM_LICENSE_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | License Key (Environment Fallback)
    |--------------------------------------------------------------------------
    |
    | Optional fallback license key read from .env.
    | Used when no key is stored in the database (e.g. after migrate:fresh).
    | The database-stored key always takes precedence.
    |
    */

    'key' => env('ANYPIM_LICENSE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Enterprise Modules
    |--------------------------------------------------------------------------
    |
    | Each module can be activated via a valid license key.
    | Without a license, the Community Edition runs fully functional
    | but enterprise-only routes return 403.
    |
    */

    'modules' => [
        'bmecat' => [
            'name' => 'BMEcat Import/Export',
            'description' => 'BMEcat XML 1.2 / 2005 Import und Export',
        ],
        'content' => [
            'name' => 'Strukturierter Content (CMS)',
            'description' => 'Content-Seiten, Sektionstypen, Navigationsbaum/Sitemap und Website-Vorschau',
        ],
        'advanced_export' => [
            'name' => 'Erweiterte Exports',
            'description' => 'Export-Jobs, Zeitpläne, SFTP/HTTP-Delivery, Export-Mappings',
        ],
        'publixx' => [
            'name' => 'Publixx-Integration',
            'description' => 'Publixx-Mappings',
        ],
        'reports' => [
            'name' => 'Berichte',
            'description' => 'Bericht-Designer und Auswertungen',
        ],
        'pdf_templates' => [
            'name' => 'PDF-Vorlagen',
            'description' => 'PDF-Template-Designer und -Generierung',
        ],
        'sso' => [
            'name' => 'Single Sign-On',
            'description' => 'Azure AD / OAuth SSO-Integration',
        ],
        'workflow' => [
            'name' => 'Workflow-Management',
            'description' => 'Konfigurierbare Workflows, Teams und Projekte',
        ],
        'api_designer' => [
            'name' => 'API-Designer',
            'description' => 'Visueller API-Designer mit JSON-Streaming-Endpoints',
        ],
        'excel_designer' => [
            'name' => 'Sheet Designer',
            'description' => 'Excel-Sheet-Designer für konfigurierbare .xlsx-Produktlisten mit Thumbnails',
        ],
        'connectors' => [
            'name' => 'Connectoren',
            'description' => 'Externe API-Connectoren (Shopware, Shopify, DeepL, Claude AI u.a.)',
        ],
        'catalog_templates' => [
            'name' => 'Katalog-Vorlagen',
            'description' => 'Catalog-Embed-System mit konfigurierbaren HTML-Vorlagen und Widgets',
        ],
        'typo3' => [
            'name' => 'TYPO3-Integration',
            'description' => 'Anleitung zur Einbindung des Catalog-Embed-Widgets in TYPO3-Websites',
        ],
        'ecommerce' => [
            'name' => 'E-Commerce',
            'description' => 'Warenkorbarten, Adress- & Zahlungsverwaltung, Bestellabwicklung für KMUs',
        ],
        'collections' => [
            'name' => 'Collections',
            'description' => 'Kuratierte, empfängerbezogene Produktkollektionen (Angebote, RFQ, Kataloge)',
        ],
    ],

];
