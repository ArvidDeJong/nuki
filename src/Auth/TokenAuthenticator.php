<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Darvis\Nuki\Contracts\ApiTokenResolver;
use Darvis\Nuki\Contracts\Authenticator;
use Darvis\Nuki\Exceptions\AuthenticationException;
use Illuminate\Http\Client\PendingRequest;

class TokenAuthenticator implements Authenticator
{
    public function __construct(private readonly ApiTokenResolver $resolver) {}

    public function apply(PendingRequest $request, string $accountKey): PendingRequest
    {
        $token = $this->resolver->resolve($accountKey);

        if (empty($token)) {
            throw new AuthenticationException(
                "No NUKI API token configured for account [{$accountKey}]. "
                .'Add it via the NUKI accounts page or set NUKI_API_TOKEN for the default account.',
            );
        }

        return $request->withToken($token);
    }
}
