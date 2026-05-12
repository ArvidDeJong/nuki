<?php

declare(strict_types=1);

namespace Darvis\Nuki\Resources;

use Darvis\Nuki\Http\HttpClient;

abstract class Resource
{
    public function __construct(
        protected readonly HttpClient $http,
        protected readonly string $accountKey = 'default',
    ) {}

    abstract protected function withAccount(string $accountKey): static;
}
