<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\DTOs\Authorization;
use Illuminate\Support\Collection;

class SmartlockAuths extends Resource
{
    public const TYPE_APP = 0;

    public const TYPE_BRIDGE = 1;

    public const TYPE_FOB = 2;

    public const TYPE_KEYPAD = 3;

    public const TYPE_KEYPAD_CODE = 13;

    public const TYPE_Z_KEY = 14;

    /**
     * @return Collection<int, Authorization>
     */
    public function forSmartlock(int $smartlockId, array $filters = []): Collection
    {
        $data = $this->http
            ->get("/smartlock/{$smartlockId}/auth", $filters, $this->accountKey)
            ->json();

        return collect($data ?? [])->map(fn (array $row) => Authorization::fromArray($row));
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function all(array $filters = []): Collection
    {
        $data = $this->http->get('/smartlock/auth', $filters, $this->accountKey)->json();

        return collect($data ?? [])->map(fn (array $row) => Authorization::fromArray($row));
    }

    public function create(int $smartlockId, array $attributes): void
    {
        $this->http->put("/smartlock/{$smartlockId}/auth", $attributes, $this->accountKey);
    }

    public function update(int $smartlockId, string $authId, array $attributes): void
    {
        $this->http->post("/smartlock/{$smartlockId}/auth/{$authId}", $attributes, $this->accountKey);
    }

    public function delete(int $smartlockId, string $authId): void
    {
        $this->http->delete("/smartlock/{$smartlockId}/auth/{$authId}", [], $this->accountKey);
    }

    protected function withAccount(string $accountKey): static
    {
        return new static($this->http, $accountKey);
    }
}
