<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('SSO_ENABLED', false),

    'auto_provision' => (bool) env('SSO_AUTO_PROVISION', true),

    'default_role' => env('SSO_DEFAULT_ROLE', 'Viewer'),

    /*
    | Optionale Allowlist von E-Mail-Domains (kommagetrennt), die sich per SSO
    | verknuepfen/provisionieren duerfen. Verhindert Account-Takeover, wenn der
    | Azure-Login gegen einen Multi-Tenant-Endpoint laeuft (ein Angreifer koennte
    | sonst in einem eigenen Tenant ein Konto mit fremder E-Mail anlegen und sich
    | mit einem bestehenden PIM-Konto verknuepfen). Leer = keine Domain-Beschraenkung.
    */
    'allowed_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SSO_ALLOWED_DOMAINS', '')),
    ))),

];
