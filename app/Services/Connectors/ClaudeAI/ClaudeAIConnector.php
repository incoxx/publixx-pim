<?php

declare(strict_types=1);

namespace App\Services\Connectors\ClaudeAI;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;

class ClaudeAIConnector extends AbstractConnector
{
    public function __construct(
        private readonly ClaudeAITextService $textService,
    ) {}

    public function getType(): string
    {
        return 'claude-ai';
    }

    public function getName(): string
    {
        return 'Claude AI';
    }

    public function getDescription(): string
    {
        return 'Claude AI – KI-generierte Produktbeschreibungen und SEO-Texte';
    }

    public function getCapabilities(): array
    {
        return ['text_generation', 'product_data'];
    }

    public function isConfigured(): bool
    {
        return ! empty(config('connectors.claude_ai.api_key'));
    }

    public function getAuthorizationUrl(): array
    {
        // API key auth, no OAuth
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null): array
    {
        return [
            'access_token'  => $code,
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
        throw new \RuntimeException('Claude AI unterstützt keinen Asset-Upload.');
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $apiKey = $connection->access_token ?: config('connectors.claude_ai.api_key');
        $language = $options['language'] ?? 'de';
        $task = $options['task'] ?? 'description'; // description, seo, features, marketing

        $result = $this->textService->generateProductText(
            $apiKey,
            $product,
            $language,
            $task,
            $options,
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
                'task'     => $task,
                'language' => $language,
                'tokens'   => $result['usage'] ?? null,
            ],
        );

        return $result;
    }
}
