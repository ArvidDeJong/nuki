<?php

declare(strict_types=1);

namespace Darvis\Nuki\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NukiWebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $type,
        public readonly array $payload,
        public readonly ?string $accountKey = null,
    ) {}
}
