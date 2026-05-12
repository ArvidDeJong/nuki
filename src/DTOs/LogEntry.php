<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class LogEntry
{
    public function __construct(
        public string $id,
        public int $smartlockId,
        public ?int $accountUserId,
        public ?int $authId,
        public ?string $authType,
        public ?string $name,
        public int $action,
        public ?int $trigger,
        public ?int $state,
        public ?bool $autoUnlock,
        public ?CarbonImmutable $date,
        public ?string $source,
        public array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            smartlockId: (int) ($data['smartlockId'] ?? 0),
            accountUserId: isset($data['accountUserId']) ? (int) $data['accountUserId'] : null,
            authId: isset($data['authId']) ? (int) $data['authId'] : null,
            authType: $data['authType'] ?? null,
            name: $data['name'] ?? null,
            action: (int) ($data['action'] ?? 0),
            trigger: isset($data['trigger']) ? (int) $data['trigger'] : null,
            state: isset($data['state']) ? (int) $data['state'] : null,
            autoUnlock: $data['autoUnlock'] ?? null,
            date: isset($data['date']) ? CarbonImmutable::parse($data['date']) : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            raw: $data,
        );
    }
}
