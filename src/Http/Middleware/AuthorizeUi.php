<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Middleware;

use Closure;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides who may open the bundled UI when the package does not bring its own users.
 *
 * With `auth_users.enabled` the `darvis-nuki` guard already protects every page, so this
 * middleware steps aside. Without it, the `viewNuki` gate decides: the package default only
 * allows the local environment, and a host app defines the gate to let its own users in.
 * The gate is asked with the host app's signed in user, or null for a guest.
 */
class AuthorizeUi
{
    public const GATE = 'viewNuki';

    public function handle(Request $request, Closure $next): Response
    {
        if (NukiConfig::authUsersEnabled()) {
            return $next($request);
        }

        abort_unless(Gate::allows(self::GATE), 403);

        return $next($request);
    }
}
