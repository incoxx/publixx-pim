<?php

declare(strict_types=1);

namespace App\Services\Connectors\DeepL;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;

class DeepLConnector extends AbstractConnector
{
    public function __construct(
        private readonly DeepLTranslationService $translationService,
    ) {}

    public function getType(): string
    {
        return 'deepl';
    }

    public function getName(): string
    {
        return 'DeepL';
    }

    public function getDescription(): string
    {
        return 'DeepL API – Automatische Übersetzung von Produkttexten';
    }

    public function getCapabilities(): array
    {
        return ['translation', 'product_data'];
    }

    public function isConfigured(): bool
    {
        return ! empty(config('connectors.deepl.api_key'));
    }

    public function getAuthorizationUrl(): array
    {
        // DeepL uses API key auth, no OAuth flow needed
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        // API key-based: token is set directly via settings
        return [
            'access_token'  => $code, // API key passed as "code"
            'refresh_token' => null,
            'expires_in'    => null,
        ];
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        // API key does not expire
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        // DeepL does not support asset uploads
        throw new \RuntimeException('DeepL unterstützt keinen Asset-Upload.');
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $apiKey = $connection->access_token ?: config('connectors.deepl.api_key');
        $sourceLang = $options['source_language'] ?? 'de';
        $targetLang = $options['target_language'] ?? 'en';
        $attributes = $options['attributes'] ?? null;

        $result = $this->translationService->translateProduct(
            $apiKey,
            $product,
            strtoupper($sourceLang),
            strtoupper($targetLang),
            $attributes,
        );

        $this->logSync(
            $connection,
            'product_push',
            'product',
            $product->id,
            'success',
            null,
            null,
            [
                'source_language'    => $sourceLang,
                'target_language'    => $targetLang,
                'translated_fields'  => count($result['translations'] ?? []),
            ],
        );

        return $result;
    }
}
