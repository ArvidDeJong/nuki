<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Darvis\Nuki\Contracts\ApiTokenResolver;
use Darvis\Nuki\Models\NukiAccount;

class DatabaseApiTokenResolver implements ApiTokenResolver
{
    public function __construct(private readonly ?string $fallbackDefaultToken = null) {}

    public function resolve(string $accountKey): ?string
    {
        $account = NukiAccount::query()
            ->where('account_key', $accountKey)
            ->where('is_active', true)
            ->first();

        if ($account?->api_token) {
            return $account->api_token;
        }

        if ($accountKey === 'default' && filled($this->fallbackDefaultToken)) {
            return $this->fallbackDefaultToken;
        }

        return null;
    }
}
