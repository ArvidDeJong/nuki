<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\DTOs\WebhookSubscription;
use Illuminate\Support\Collection;

class Webhooks extends Resource
{
    /**
     * @return Collection<int, WebhookSubscription>
     */
    public function all(): Collection
    {
        $data = $this->http->get('/api/notification', [], $this->accountKey)->json();

        return collect($data ?? [])->map(fn (array $row) => WebhookSubscription::fromArray($row));
    }

    public function subscribe(string $callbackUrl, array $events): WebhookSubscription
    {
        $data = $this->http->put('/api/notification', [
            'referenceId' => 'darvis-nuki',
            'pushId' => $callbackUrl,
            'notificationType' => 'webhook',
            'webhookFeatures' => $events,
        ], $this->accountKey)->json();

        return WebhookSubscription::fromArray(is_array($data) ? $data : []);
    }

    public function unsubscribe(string $id): void
    {
        $this->http->delete("/api/notification/{$id}", [], $this->accountKey);
    }

    protected function withAccount(string $accountKey): static
    {
        return new static($this->http, $accountKey);
    }
}
