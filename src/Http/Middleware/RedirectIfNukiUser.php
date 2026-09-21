<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Middleware;

use Closure;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's `guest` middleware for the package guard. A package user who is already signed in and
 * opens the login page goes to `auth_users.redirect_after_login`, not to the host application's
 * `dashboard` or `home` route.
 */
class RedirectIfNukiUser extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        return parent::handle($request, $next, AuthConfigRegistrar::GUARD);
    }

    protected function redirectTo(Request $request): ?string
    {
        return NukiConfig::redirectAfterLogin();
    }
}
