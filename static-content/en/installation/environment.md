# Environment Configuration

anyPIM is configured via a `.env` file in the project root. This page documents every available variable grouped by subsystem.

::: tip
Copy `.env.example` to `.env` and adjust values to your environment. A separate `.env.production.example` is provided as a starting point for production deployments.
:::

## Application

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `anyPIM` | Application name shown in emails and UI |
| `APP_ENV` | `local` | Environment: `local`, `staging`, or `production` |
| `APP_KEY` | — | Encryption key. Generate with `php artisan key:generate` |
| `APP_DEBUG` | `true` | Show detailed error pages. **Must be `false` in production** |
| `APP_URL` | `http://localhost:8000` | Backend base URL used for URL generation |

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel (stack, single, daily, stderr, syslog) |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Channel for PHP deprecation notices |
| `LOG_LEVEL` | `debug` | Minimum level: debug, info, notice, warning, error, critical. Use `warning` in production |

## Database

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `publixx_pim` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | — | Database password |

## Redis

anyPIM uses separate Redis databases to isolate cache, queues, and sessions.

| Variable | Default | Description |
|----------|---------|-------------|
| `REDIS_CLIENT` | `phpredis` | Redis client (`phpredis` or `predis`) |
| `REDIS_HOST` | `127.0.0.1` | Redis server host |
| `REDIS_PASSWORD` | `null` | Redis password (set `null` for no auth) |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_CACHE_DB` | `1` | Redis database index for cache |
| `REDIS_QUEUE_DB` | `2` | Redis database index for queues |
| `REDIS_SESSION_DB` | `3` | Redis database index for sessions |

## Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `CACHE_STORE` | `redis` | Cache backend (`redis`, `file`, `array`) |
| `CACHE_PREFIX` | `pim` | Key prefix to avoid collisions in shared Redis |

### Cache TTL Overrides (seconds)

Fine-tune how long specific data is cached:

| Variable | Default | Description |
|----------|---------|-------------|
| `CACHE_TTL_PRODUCT_FULL` | `3600` | Full product with all relations |
| `CACHE_TTL_PRODUCT_LANG` | `3600` | Language-specific product data |
| `CACHE_TTL_HIERARCHY_TREE` | `21600` | Hierarchy tree structure (6 hours) |
| `CACHE_TTL_PQL_RESULT` | `900` | PQL query results (15 minutes) |
| `CACHE_TTL_PRODUCT_LIST` | `300` | Product listing pages (5 minutes) |
| `CACHE_TTL_ATTRIBUTES_ALL` | `3600` | Full attribute catalog |
| `CACHE_TTL_EXPORT_MAPPING` | `1800` | Export mapping config (30 minutes) |

## Queue & Horizon

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver (`redis`, `database`, `sync`) |
| `HORIZON_PREFIX` | `pim_horizon:` | Redis key prefix for Laravel Horizon |

## Session

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | `redis` | Session backend (`redis`, `file`, `database`) |
| `SESSION_LIFETIME` | `120` | Session timeout in minutes |
| `SESSION_SECURE_COOKIE` | `false` | Set to `true` in production (requires HTTPS) |

## Authentication & CORS

| Variable | Default | Description |
|----------|---------|-------------|
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,localhost:3000,...` | Comma-separated domains allowed for cookie-based auth |
| `FRONTEND_URL` | `http://localhost:3000` | Frontend origin for CORS headers |

## Filesystem

| Variable | Default | Description |
|----------|---------|-------------|
| `FILESYSTEM_DISK` | `local` | Default storage disk (`local`, `s3`) |

## Mail

| Variable | Default | Description |
|----------|---------|-------------|
| `MAIL_MAILER` | `log` | Mail driver. Use `log` for development, `smtp` for production |
| `MAIL_HOST` | — | SMTP server hostname |
| `MAIL_PORT` | `587` | SMTP port (587 for TLS, 465 for SSL) |
| `MAIL_USERNAME` | — | SMTP username |
| `MAIL_PASSWORD` | — | SMTP password |
| `MAIL_ENCRYPTION` | `tls` | Encryption method (`tls` or `ssl`) |
| `MAIL_FROM_ADDRESS` | — | Default sender email |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Default sender name |

## Translation Memory Service (TMS)

The TMS is a separate microservice that provides automated translations.

| Variable | Default | Description |
|----------|---------|-------------|
| `TMS_ENABLED` | `false` | Enable TMS integration |
| `TMS_BASE_URL` | `http://localhost:8001/api` | TMS API endpoint |
| `TMS_API_KEY` | — | Shared API key (must match `TMS_API_KEY` in TMS `.env`) |
| `TMS_TIMEOUT` | `5` | HTTP timeout in seconds |
| `TMS_TARGET_LANGUAGES` | `en,fr,es,it,nl` | Comma-separated target languages |

### TMS Service Configuration (tms/.env)

The TMS service has its own `.env` with additional settings:

| Variable | Default | Description |
|----------|---------|-------------|
| `TMS_PROVIDER_CHAIN` | `deepl,google` | Translation provider fallback chain |
| `TMS_CACHE_TTL` | `86400` | Cache duration for translations (24 hours) |
| `DEEPL_API_KEY` | — | DeepL API key |
| `DEEPL_API_URL` | `https://api-free.deepl.com/v2` | DeepL endpoint (use `api.deepl.com` for Pro) |
| `GOOGLE_TRANSLATE_API_KEY` | — | Google Translate API key |
| `ANTHROPIC_API_KEY` | — | Anthropic API key for Claude-based translations |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-6` | Claude model for AI translations |

## SSO (Azure AD / Entra ID)

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_ENABLED` | `false` | Enable Azure AD Single Sign-On |
| `SSO_AUTO_PROVISION` | `true` | Automatically create user accounts on first SSO login |
| `SSO_DEFAULT_ROLE` | `Viewer` | Default role assigned to auto-provisioned users |
| `AZURE_AD_CLIENT_ID` | — | Azure AD Application (client) ID |
| `AZURE_AD_CLIENT_SECRET` | — | Azure AD client secret |
| `AZURE_AD_TENANT_ID` | — | Azure AD tenant ID |
| `AZURE_AD_REDIRECT_URI` | `${APP_URL}/api/v1/auth/sso/callback` | OAuth callback URL |

## License

| Variable | Default | Description |
|----------|---------|-------------|
| `ANYPIM_LICENSE_PUBLIC_KEY` | — | Ed25519 public key for license signature verification |
| `ANYPIM_LICENSE_KEY` | — | Fallback license key (overridden by database-stored key) |

## Deployment

| Variable | Default | Description |
|----------|---------|-------------|
| `DEPLOY_USER` | — | System user that owns the project files. Leave empty to auto-detect from directory owner |

## Production Checklist

When deploying to production, ensure the following:

1. `APP_ENV=production` and `APP_DEBUG=false`
2. `LOG_LEVEL=warning` (avoid debug noise)
3. `SESSION_SECURE_COOKIE=true` (requires HTTPS)
4. `MAIL_MAILER=smtp` with valid SMTP credentials
5. `SANCTUM_STATEFUL_DOMAINS` matches your production domain
6. `FRONTEND_URL` matches your production frontend URL
7. `APP_KEY` is set (run `php artisan key:generate`)
8. `DB_PASSWORD` and `REDIS_PASSWORD` are set to strong values
9. All API keys (`TMS_API_KEY`, `DEEPL_API_KEY`, etc.) are production keys
10. `AZURE_AD_*` variables are set if SSO is enabled
