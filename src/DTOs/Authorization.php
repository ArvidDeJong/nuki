<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class Authorization
{
    public function __construct(
        public string $id,
        public ?int $smartlockId,
        public ?int $authId,
        public ?int $code,
        public int $type,
        public string $name,
        public ?bool $enabled,
        public ?bool $remoteAllowed,
        public ?CarbonImmutable $allowedFromDate,
        public ?CarbonImmutable $allowedUntilDate,
        public ?int $allowedWeekDays,
        public ?CarbonImmutable $lastActiveDate,
        public ?CarbonImmutable $creationDate,
        public ?CarbonImmutable $updateDate,
        public array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            smartlockId: isset($data['smartlockId']) ? (int) $data['smartlockId'] : null,
            authId: isset($data['authId']) ? (int) $data['authId'] : null,
            code: isset($data['code']) ? (int) $data['code'] : null,
            type: (int) ($data['type'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            enabled: $data['enabled'] ?? null,
            remoteAllowed: $data['remoteAllowed'] ?? null,
            allowedFromDate: isset($data['allowedFromDate']) ? CarbonImmutable::parse($data['allowedFromDate']) : null,
            allowedUntilDate: isset($data['allowedUntilDate']) ? CarbonImmutable::parse($data['allowedUntilDate']) : null,
            allowedWeekDays: isset($data['allowedWeekDays']) ? (int) $data['allowedWeekDays'] : null,
            lastActiveDate: isset($data['lastActiveDate']) ? CarbonImmutable::parse($data['lastActiveDate']) : null,
            creationDate: isset($data['creationDate']) ? CarbonImmutable::parse($data['creationDate']) : null,
            updateDate: isset($data['updateDate']) ? CarbonImmutable::parse($data['updateDate']) : null,
            raw: $data,
        );
    }
}
