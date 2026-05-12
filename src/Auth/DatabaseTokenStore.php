<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\DTOs\NukiToken;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Crypt;

class DatabaseTokenStore implements TokenStore
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'nuki_oauth_tokens',
    ) {}

    public function get(string $accountKey): ?NukiToken
    {
        $row = $this->db->table($this->table)
            ->where('account_key', $accountKey)
            ->first();

        if ($row === null) {
            return null;
        }

        return new NukiToken(
            accessToken: Crypt::decryptString($row->access_token),
            refreshToken: $row->refresh_token ? Crypt::decryptString($row->refresh_token) : null,
            expiresAt: CarbonImmutable::parse($row->expires_at),
            tokenType: $row->token_type ?? 'Bearer',
            scope: $row->scope ?? null,
        );
    }

    public function put(string $accountKey, NukiToken $token): void
    {
        $this->db->table($this->table)->updateOrInsert(
            ['account_key' => $accountKey],
            [
                'access_token' => Crypt::encryptString($token->accessToken),
                'refresh_token' => $token->refreshToken ? Crypt::encryptString($token->refreshToken) : null,
                'expires_at' => $token->expiresAt->toDateTimeString(),
                'token_type' => $token->tokenType,
                'scope' => $token->scope,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function forget(string $accountKey): void
    {
        $this->db->table($this->table)->where('account_key', $accountKey)->delete();
    }
}
