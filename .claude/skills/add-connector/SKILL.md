---
name: add-connector
description: Neuen Plattform-Connector integrieren (OAuth/API-Key, Sync, Audit)
disable-model-invocation: true
---

# Neuen Connector integrieren

Erstelle einen neuen Plattform-Connector nach dem anyPIM ConnectorInterface-Pattern.

## Schritt 1: Anforderungen klären

Frage den Nutzer:
1. **Plattform-Name** (z.B. "Magento", "WooCommerce", "Akeneo")
2. **Auth-Typ**: API-Key oder OAuth 2.0?
3. **Capabilities**: Welche Features? (`asset_upload`, `product_data`, `translation`, `category_sync`)
4. **API-Dokumentation**: URL zur API-Referenz der Plattform
5. **Braucht Sub-Services?** (z.B. separate ProductService, CategoryService, MediaService)

## Schritt 2: Dateien erstellen

### 2a. Connector-Klasse — `app/Services/Connectors/{Platform}/{Platform}Connector.php`

Muss `ConnectorInterface` implementieren (10 Methoden):

```php
<?php
declare(strict_types=1);
namespace App\Services\Connectors\{Platform};

use App\Services\Connectors\AbstractConnector;
use App\Services\Connectors\ConnectorInterface;

class {Platform}Connector extends AbstractConnector implements ConnectorInterface
{
    public function getType(): string { return '{platform}'; }
    public function getName(): string { return '{Platform}'; }
    public function getDescription(): string { return '...'; }
    public function getCapabilities(): array { return ['product_data', 'asset_upload']; }
    public function isConfigured(): bool { ... }
    public function getAuthorizationUrl(): array { ... }
    public function handleCallback($code, $codeVerifier = null, $shopUrl = null): array { ... }
    public function refreshToken($connection): void { ... }
    public function uploadAsset($connection, $media): string { ... }
    public function pushProductData($connection, $product, $options = []): array { ... }
}
```

**Auth-Typ Patterns:**

API-Key:
```php
public function isConfigured(): bool {
    return !empty(config('connectors.{platform}.api_key'));
}
public function handleCallback($code, $codeVerifier = null, $shopUrl = null): array {
    return ['access_token' => $code, 'refresh_token' => null, 'expires_in' => null];
}
public function refreshToken($connection): void { /* No-op */ }
```

OAuth 2.0:
```php
public function getAuthorizationUrl(): array {
    $state = Str::random(40);
    return [
        'url' => config('connectors.{platform}.auth_url') . '?' . http_build_query([...]),
        'state' => $state,
    ];
}
public function handleCallback($code, $codeVerifier = null, $shopUrl = null): array {
    $response = Http::post(config('connectors.{platform}.token_url'), [...]);
    return $response->json(); // access_token, refresh_token, expires_in
}
```

### 2b. Sub-Services (falls nötig)

`app/Services/Connectors/{Platform}/{Platform}ProductService.php` etc.

### 2c. Config — `config/connectors.php`

Füge neuen Block hinzu:
```php
'{platform}' => [
    'api_key'   => env('{PLATFORM}_API_KEY', ''),
    'base_url'  => env('{PLATFORM}_BASE_URL', ''),
    // OAuth:
    'client_id'     => env('{PLATFORM}_CLIENT_ID', ''),
    'client_secret' => env('{PLATFORM}_CLIENT_SECRET', ''),
    'auth_url'      => env('{PLATFORM}_AUTH_URL', ''),
    'token_url'     => env('{PLATFORM}_TOKEN_URL', ''),
],
```

### 2d. Service Provider — `app/Providers/ConnectorServiceProvider.php`

Registriere im `register()`:
```php
$this->app->singleton({Platform}Connector::class, fn ($app) => new {Platform}Connector(...));
```

Registriere im `boot()`:
```php
$registry->register($this->app->make({Platform}Connector::class));
```

## Schritt 3: Checkliste

- [ ] Implementiert `ConnectorInterface` vollständig (10 Methoden)
- [ ] Erweitert `AbstractConnector` (für `authenticatedRequest()`, `logSync()`, etc.)
- [ ] Config in `config/connectors.php` mit env-Variablen
- [ ] Registriert in `ConnectorServiceProvider` (register + boot)
- [ ] `declare(strict_types=1)`
- [ ] Token-Refresh implementiert (OAuth) oder No-op (API-Key)
- [ ] `logSync()` für alle Sync-Operationen aufgerufen
- [ ] Error-Handling mit sprechenden Fehlermeldungen
- [ ] Sensitive Daten (Tokens, Keys) nie in Logs

## Referenz

Lies diese Dateien als Vorlage:
- `app/Services/Connectors/ConnectorInterface.php` — Interface
- `app/Services/Connectors/AbstractConnector.php` — Basis-Klasse
- `app/Services/Connectors/DeepL/DeepLConnector.php` — Einfaches Beispiel (API-Key)
- `app/Services/Connectors/Shopify/ShopifyConnector.php` — Komplexes Beispiel (OAuth)
- `app/Providers/ConnectorServiceProvider.php` — Registrierung
