<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class NukiToken
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public CarbonImmutable $expiresAt,
        public ?string $tokenType = 'Bearer',
        public ?string $scope = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $expiresIn = (int) ($data['expires_in'] ?? 0);
        $expiresAt = isset($data['expires_at'])
            ? CarbonImmutable::parse($data['expires_at'])
            : CarbonImmutable::now()->addSeconds($expiresIn > 0 ? $expiresIn : 3600);

        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: $expiresAt,
            tokenType: $data['token_type'] ?? 'Bearer',
            scope: $data['scope'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'token_type' => $this->tokenType,
            'scope' => $this->scope,
        ];
    }

    public function isExpired(int $leewaySeconds = 30): bool
    {
        return $this->expiresAt->subSeconds($leewaySeconds)->isPast();
    }
}
