<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Darvis\Nuki\Contracts\ApiTokenResolver;

class ConfigApiTokenResolver implements ApiTokenResolver
{
    public function __construct(private readonly ?string $defaultToken) {}

    public function resolve(string $accountKey): ?string
    {
        if ($accountKey === 'default') {
            return $this->defaultToken ?: null;
        }

        return null;
    }
}
