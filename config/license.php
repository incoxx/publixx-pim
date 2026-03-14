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
        'advanced_export' => [
            'name' => 'Erweiterte Exports',
            'description' => 'Export-Jobs, Zeitpläne, SFTP/HTTP-Delivery, Export-Mappings',
        ],
        'publixx' => [
            'name' => 'Publixx-Integration',
            'description' => 'Publixx-Mappings und PXF-Vorlagen',
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
    ],

];
