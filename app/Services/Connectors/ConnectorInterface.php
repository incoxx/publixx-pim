<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;

interface ConnectorInterface
{
    /**
     * Eindeutiger Typ-Bezeichner (z.B. 'shopware', 'shopify', 'deepl').
     */
    public function getType(): string;

    /**
     * Anzeigename für die UI.
     */
    public function getName(): string;

    /**
     * Beschreibung des Connectors.
     */
    public function getDescription(): string;

    /**
     * Capabilities dieses Connectors (z.B. ['asset_upload', 'product_data', 'design_create']).
     */
    public function getCapabilities(): array;

    /**
     * Prüft ob der Connector konfiguriert ist (Credentials vorhanden).
     */
    public function isConfigured(): bool;

    /**
     * Generiert die OAuth-Autorisierungs-URL.
     *
     * @return array{url: string, state: string, code_verifier: string|null}
     */
    public function getAuthorizationUrl(): array;

    /**
     * Verarbeitet den OAuth-Callback und gibt Token-Daten zurück.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array;

    /**
     * Erneuert den Access-Token.
     */
    public function refreshToken(ConnectorConnection $connection): void;

    /**
     * Lädt ein Media-Asset zum externen Dienst hoch.
     *
     * @return string Externe Asset-ID
     */
    public function uploadAsset(ConnectorConnection $connection, Media $media): string;

    /**
     * Überträgt Produktdaten zum externen Dienst.
     *
     * @param  array  $options  Connector-spezifische Optionen (z.B. Attribut-Auswahl, Sprache)
     * @return array  Ergebnis mit externen IDs/Status
     */
    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array;
}
