<?php

declare(strict_types=1);

namespace Darvis\Nuki\Contracts;

use Darvis\Nuki\DTOs\NukiToken;

interface TokenStore
{
    public function get(string $accountKey): ?NukiToken;

    public function put(string $accountKey, NukiToken $token): void;

    public function forget(string $accountKey): void;
}
