<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\DTOs\SmartLock;
use Illuminate\Support\Collection;

class SmartLocks extends Resource
{
    public const ACTION_UNLOCK = 1;

    public const ACTION_LOCK = 2;

    public const ACTION_UNLATCH = 3;

    public const ACTION_LOCK_AND_GO = 4;

    public const ACTION_LOCK_AND_GO_WITH_UNLATCH = 5;

    /**
     * @return Collection<int, SmartLock>
     */
    public function all(array $query = []): Collection
    {
        $data = $this->http->get('/smartlock', $query, $this->accountKey)->json();

        return collect($data ?? [])->map(fn (array $row) => SmartLock::fromArray($row));
    }

    public function find(int $smartlockId): SmartLock
    {
        $data = $this->http->get("/smartlock/{$smartlockId}", [], $this->accountKey)->json();

        return SmartLock::fromArray($data);
    }

    public function lock(int $smartlockId): void
    {
        $this->action($smartlockId, self::ACTION_LOCK);
    }

    public function unlock(int $smartlockId): void
    {
        $this->action($smartlockId, self::ACTION_UNLOCK);
    }

    public function unlatch(int $smartlockId): void
    {
        $this->action($smartlockId, self::ACTION_UNLATCH);
    }

    public function lockAndGo(int $smartlockId): void
    {
        $this->action($smartlockId, self::ACTION_LOCK_AND_GO);
    }

    public function lockAndGoWithUnlatch(int $smartlockId): void
    {
        $this->action($smartlockId, self::ACTION_LOCK_AND_GO_WITH_UNLATCH);
    }

    public function action(int $smartlockId, int $action, ?int $option = null): void
    {
        $body = ['action' => $action];

        if ($option !== null) {
            $body['option'] = $option;
        }

        $this->http->post("/smartlock/{$smartlockId}/action", $body, $this->accountKey);
    }

    /**
     * Update one of the user-controllable smartlock fields.
     *
     * Supported attributes per the NUKI Web API: `name`, `favourite`,
     * `defaultName` and a handful of advanced flags. Pass only the keys
     * you want to change; NUKI merges them server-side.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $smartlockId, array $attributes): void
    {
        $this->http->post("/smartlock/{$smartlockId}", $attributes, $this->accountKey);
    }

    /**
     * Request a state-sync from the bridge / Wi-Fi-equipped device so the
     * cached `state` we read back is fresh.
     */
    public function sync(int $smartlockId): void
    {
        $this->http->post("/smartlock/{$smartlockId}/sync", [], $this->accountKey);
    }

    protected function withAccount(string $accountKey): static
    {
        return new static($this->http, $accountKey);
    }
}
