<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure AD / Entra ID (SSO via OIDC)
    |--------------------------------------------------------------------------
    */

    'azure' => [
        'client_id' => env('AZURE_AD_CLIENT_ID'),
        'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
        'redirect' => env('AZURE_AD_REDIRECT_URI'),
        'tenant' => env('AZURE_AD_TENANT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fehlerklassifikation: Weiterleiten-Benachrichtigungen
    |--------------------------------------------------------------------------
    | ERROR_FORWARD_EMAIL    — Empfänger-Adresse für "An Entwicklung weiterleiten"
    | ERROR_FORWARD_SLACK_WEBHOOK — Slack Incoming Webhook URL
    | Beide Werte sind optional; nicht gesetzte Kanäle werden still übersprungen.
    */

    'error_forward' => [
        'email'         => env('ERROR_FORWARD_EMAIL'),
        'slack_webhook' => env('ERROR_FORWARD_SLACK_WEBHOOK'),
    ],

];
