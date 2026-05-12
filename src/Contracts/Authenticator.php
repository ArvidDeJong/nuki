<?php

declare(strict_types=1);

namespace Darvis\Nuki\Contracts;

use Illuminate\Http\Client\PendingRequest;

interface Authenticator
{
    /**
     * Apply credentials (typically a Bearer token) to the request.
     */
    public function apply(PendingRequest $request, string $accountKey): PendingRequest;
}
