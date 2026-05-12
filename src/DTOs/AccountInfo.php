<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class AccountInfo
{
    public function __construct(
        public int $accountId,
        public ?string $email,
        public ?string $name,
        public ?string $language,
        public ?CarbonImmutable $creationDate,
        public array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) ($data['accountId'] ?? 0),
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            language: $data['language'] ?? null,
            creationDate: isset($data['creationDate']) ? CarbonImmutable::parse($data['creationDate']) : null,
            raw: $data,
        );
    }

    public function displayName(): string
    {
        return $this->name ?: $this->email ?: ('#'.$this->accountId);
    }
}
