<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class WebhookSubscription
{
    public function __construct(
        public string $id,
        public string $callbackUrl,
        public array $events,
        public ?CarbonImmutable $creationDate,
        public array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            callbackUrl: (string) ($data['callbackUrl'] ?? $data['url'] ?? ''),
            events: $data['webhookFeatures'] ?? $data['events'] ?? [],
            creationDate: isset($data['creationDate']) ? CarbonImmutable::parse($data['creationDate']) : null,
            raw: $data,
        );
    }
}
