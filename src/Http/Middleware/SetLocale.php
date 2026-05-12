<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a locale for the package UI and applies it to both Laravel
 * (App::setLocale) and Carbon (date/time formatting + diffForHumans).
 *
 * Resolution order:
 *   1. Authenticated NukiUser->locale (when auth_users is enabled)
 *   2. session('nuki.locale')
 *   3. app()->getLocale() if it is in the allow-list
 *   4. config('nuki.ui.default_locale')
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale();
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(): string
    {
        $allowed = array_keys((array) config('nuki.ui.locales', ['en' => 'English']));
        $default = (string) config('nuki.ui.default_locale', config('app.locale', 'en'));

        if (config('nuki.auth_users.enabled') === true) {
            $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();
            if ($user !== null) {
                $userLocale = (string) ($user->locale ?? '');
                if ($userLocale !== '' && in_array($userLocale, $allowed, true)) {
                    return $userLocale;
                }
            }
        }

        $session = (string) session('nuki.locale', '');
        if ($session !== '' && in_array($session, $allowed, true)) {
            return $session;
        }

        $app = (string) app()->getLocale();
        if (in_array($app, $allowed, true)) {
            return $app;
        }

        return in_array($default, $allowed, true) ? $default : ($allowed[0] ?? 'en');
    }
}
