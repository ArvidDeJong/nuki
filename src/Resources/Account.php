<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\DTOs\AccountInfo;
use Illuminate\Support\Facades\Cache;

class Account extends Resource
{
    public function info(bool $fresh = false): ?AccountInfo
    {
        $key = "nuki:account-info:{$this->accountKey}";

        if ($fresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);
        if ($cached instanceof AccountInfo) {
            return $cached;
        }

        try {
            $data = $this->http->get('/account', [], $this->accountKey)->json();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data) || empty($data)) {
            return null;
        }

        $info = AccountInfo::fromArray($data);
        Cache::put($key, $info, 3600);

        return $info;
    }

    protected function withAccount(string $accountKey): static
    {
        return new static($this->http, $accountKey);
    }
}
