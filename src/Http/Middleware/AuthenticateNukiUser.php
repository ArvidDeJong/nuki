<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Middleware;

use Closure;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Laravel's `auth` middleware for the package guard, with one difference: a guest goes to the
 * package's own login page. The plain middleware sends a guest to the host application's `login`
 * route, which is another login, or no route at all.
 *
 * It only overrides where the redirect goes, so the host application's own `redirectGuestsTo()`
 * is left alone and keeps working for its own pages.
 */
class AuthenticateNukiUser extends Authenticate
{
    /**
     * @param  Request  $request
     * @param  string  ...$guards  Ignored: this middleware is for the package guard only.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        return parent::handle($request, $next, AuthConfigRegistrar::GUARD);
    }

    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('nuki.auth.login');
    }
}
