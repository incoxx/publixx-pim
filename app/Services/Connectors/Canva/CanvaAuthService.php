<?php

declare(strict_types=1);

namespace App\Services\Connectors\Canva;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CanvaAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUrl;
    private string $tokenUrl;
    private array $scopes;

    public function __construct()
    {
        $this->clientId     = config('connectors.canva.client_id');
        $this->clientSecret = config('connectors.canva.client_secret');
        $this->redirectUri  = config('connectors.canva.redirect_uri');
        $this->authUrl      = config('connectors.canva.auth_url');
        $this->tokenUrl     = config('connectors.canva.token_url');
        $this->scopes       = config('connectors.canva.scopes', []);
    }

    /**
     * Generiert PKCE Code-Verifier und Code-Challenge.
     *
     * @return array{verifier: string, challenge: string}
     */
    public function generatePkce(): array
    {
        $verifier = Str::random(128);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'verifier'  => $verifier,
            'challenge' => $challenge,
        ];
    }

    /**
     * Erstellt die OAuth-Autorisierungs-URL mit PKCE.
     *
     * @return array{url: string, state: string, code_verifier: string}
     */
    public function getAuthorizationUrl(): array
    {
        $state = Str::random(40);
        $pkce = $this->generatePkce();

        $params = http_build_query([
            'response_type'         => 'code',
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'scope'                 => implode(' ', $this->scopes),
            'state'                 => $state,
            'code_challenge'        => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ]);

        return [
            'url'           => "{$this->authUrl}?{$params}",
            'state'         => $state,
            'code_verifier' => $pkce['verifier'],
        ];
    }

    /**
     * Tauscht den Autorisierungscode gegen Tokens.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->tokenUrl, [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri,
                'code_verifier' => $codeVerifier,
            ]);

        $response->throw();
        $data = $response->json();

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in'    => $data['expires_in'] ?? null,
        ];
    }

    /**
     * Erneuert den Access-Token mit dem Refresh-Token.
     */
    public function refreshAccessToken(ConnectorConnection $connection): array
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->tokenUrl, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        $response->throw();
        $data = $response->json();

        $connection->update([
            'access_token'     => $data['access_token'],
            'refresh_token'    => $data['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : null,
        ]);

        return $data;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }
}
