<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\DTOs\LogEntry;
use Illuminate\Support\Collection;

class SmartlockLogs extends Resource
{
    /**
     * @return Collection<int, LogEntry>
     */
    public function forSmartlock(int $smartlockId, array $filters = []): Collection
    {
        $data = $this->http
            ->get("/smartlock/{$smartlockId}/log", $filters, $this->accountKey)
            ->json();

        return collect($data ?? [])->map(fn (array $row) => LogEntry::fromArray($row));
    }

    /**
     * Account-wide logs across all smartlocks.
     *
     * @return Collection<int, LogEntry>
     */
    public function all(array $filters = []): Collection
    {
        $data = $this->http->get('/smartlock/log', $filters, $this->accountKey)->json();

        return collect($data ?? [])->map(fn (array $row) => LogEntry::fromArray($row));
    }

    protected function withAccount(string $accountKey): static
    {
        return new static($this->http, $accountKey);
    }
}
