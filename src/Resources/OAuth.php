<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\DTOs\NukiToken;
use Darvis\Nuki\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;

class OAuth
{
    public function __construct(
        private readonly array $config,
        private readonly TokenStore $tokens,
    ) {}

    public function authorizationUrl(?string $state = null, ?array $scopes = null): string
    {
        $this->requireOAuthConfig();

        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_url'],
            'response_type' => 'code',
            'scope' => implode(' ', $scopes ?? $this->config['scopes'] ?? []),
        ];

        if ($state !== null) {
            $params['state'] = $state;
        }

        return $this->config['authorize_url'].'?'.http_build_query($params);
    }

    public function exchangeCode(string $code, string $accountKey = 'default'): NukiToken
    {
        $this->requireOAuthConfig();

        $response = Http::asForm()->post($this->config['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_url'],
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        if (! $response->successful()) {
            throw new AuthenticationException(
                'NUKI OAuth code exchange failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        $token = NukiToken::fromArray($response->json());
        $this->tokens->put($accountKey, $token);

        return $token;
    }

    public function refresh(string $accountKey = 'default'): NukiToken
    {
        $this->requireOAuthConfig();

        $existing = $this->tokens->get($accountKey);

        if ($existing === null || $existing->refreshToken === null) {
            throw new AuthenticationException(
                "No refreshable NUKI token stored for account [{$accountKey}].",
            );
        }

        $response = Http::asForm()->post($this->config['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $existing->refreshToken,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        if (! $response->successful()) {
            throw new AuthenticationException(
                'NUKI OAuth refresh failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        $payload = $response->json();
        $payload['refresh_token'] ??= $existing->refreshToken;

        $new = NukiToken::fromArray($payload);
        $this->tokens->put($accountKey, $new);

        return $new;
    }

    public function token(string $accountKey = 'default'): ?NukiToken
    {
        return $this->tokens->get($accountKey);
    }

    public function revoke(string $accountKey = 'default'): void
    {
        $this->tokens->forget($accountKey);
    }

    private function requireOAuthConfig(): void
    {
        foreach (['client_id', 'client_secret', 'redirect_url', 'authorize_url', 'token_url'] as $key) {
            if (empty($this->config[$key])) {
                throw new AuthenticationException(
                    "Missing NUKI OAuth config key: {$key}. Check config/nuki.php and your environment variables.",
                );
            }
        }
    }
}
