<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connector-Konfigurationen
    |--------------------------------------------------------------------------
    |
    | Jeder Connector wird hier mit seinen API-Credentials und Einstellungen
    | konfiguriert. Credentials kommen aus der .env-Datei.
    |
    */

    'deepl' => [
        'api_key'  => env('DEEPL_API_KEY', ''),
        'base_url' => env('DEEPL_BASE_URL', 'https://api-free.deepl.com/v2'),
    ],

    'shopware' => [
        'shop_url'      => env('SHOPWARE_SHOP_URL', ''),
        'client_id'     => env('SHOPWARE_CLIENT_ID', ''),
        'client_secret' => env('SHOPWARE_CLIENT_SECRET', ''),
    ],

    'shopify' => [
        'shop_url'      => env('SHOPIFY_SHOP_URL', ''),        // z.B. https://mein-shop.myshopify.com
        'access_token'  => env('SHOPIFY_ACCESS_TOKEN', ''),     // Legacy: statischer Admin API Access Token
        'client_id'     => env('SHOPIFY_CLIENT_ID', ''),        // Neu (ab 2026): Client ID
        'client_secret' => env('SHOPIFY_CLIENT_SECRET', ''),    // Neu (ab 2026): Client Secret
    ],

    'claude_ai' => [
        'api_key'    => env('CLAUDE_AI_API_KEY', ''),
        'base_url'   => env('CLAUDE_AI_BASE_URL', 'https://api.anthropic.com/v1'),
        'model'      => env('CLAUDE_AI_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => env('CLAUDE_AI_MAX_TOKENS', 1024),
    ],

    'openai' => [
        'api_key'    => env('OPENAI_API_KEY', ''),
        'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model'      => env('OPENAI_MODEL', 'gpt-4o'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
    ],

    // In-App Copilot (Chat-Fenster im PIM). Nutzt den Claude-Key wieder und
    // bindet den eigenen MCP-Endpoint via Anthropic MCP-Connector ein.
    // WICHTIG: copilot.mcp_url muss von Anthropic öffentlich erreichbar sein.
    'copilot' => [
        'api_key'    => env('COPILOT_API_KEY', env('CLAUDE_AI_API_KEY', '')),
        'model'      => env('COPILOT_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => env('COPILOT_MAX_TOKENS', 8192),
        'mcp_url'    => env('COPILOT_MCP_URL', rtrim((string) env('APP_URL', ''), '/') . '/api/v1/mcp'),
    ],

    'jira' => [
        'url'         => env('JIRA_URL', ''),          // https://your-domain.atlassian.net
        'email'       => env('JIRA_EMAIL', ''),         // user@example.com
        'api_token'   => env('JIRA_API_TOKEN', ''),     // Atlassian API Token
        'project_key' => env('JIRA_PROJECT_KEY', ''),   // z.B. DEV
        'issue_type'  => env('JIRA_ISSUE_TYPE', 'Bug'),
    ],

];
