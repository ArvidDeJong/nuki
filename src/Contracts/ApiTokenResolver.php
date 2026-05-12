<?php

declare(strict_types=1);

namespace Darvis\Nuki\Contracts;

interface ApiTokenResolver
{
    public function resolve(string $accountKey): ?string;
}
