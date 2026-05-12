<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Darvis\Nuki\Contracts\Authenticator;
use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\DTOs\NukiToken;
use Darvis\Nuki\Exceptions\AuthenticationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OAuthAuthenticator implements Authenticator
{
    public function __construct(
        private readonly TokenStore $tokens,
        private readonly array $config,
    ) {}

    public function apply(PendingRequest $request, string $accountKey): PendingRequest
    {
        $token = $this->tokens->get($accountKey);

        if ($token === null) {
            throw new AuthenticationException(
                "No NUKI OAuth token stored for account [{$accountKey}]. Run nuki:oauth-authorize or complete the OAuth flow first.",
            );
        }

        if ($token->isExpired() && $token->refreshToken !== null) {
            $token = $this->refresh($token, $accountKey);
        }

        if ($token->isExpired()) {
            throw new AuthenticationException(
                "NUKI OAuth token for account [{$accountKey}] is expired and could not be refreshed.",
            );
        }

        return $request->withToken($token->accessToken, $token->tokenType ?? 'Bearer');
    }

    public function refresh(NukiToken $token, string $accountKey): NukiToken
    {
        if ($token->refreshToken === null) {
            throw new AuthenticationException('Cannot refresh: refresh_token is null.');
        }

        $response = Http::asForm()->post($this->config['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refreshToken,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        if (! $response->successful()) {
            $this->tokens->forget($accountKey);
            throw new AuthenticationException(
                'Failed to refresh NUKI OAuth token: HTTP '.$response->status().' '.$response->body(),
            );
        }

        $payload = $response->json();
        $payload['refresh_token'] ??= $token->refreshToken;

        $new = NukiToken::fromArray($payload);
        $this->tokens->put($accountKey, $new);

        return $new;
    }
}
