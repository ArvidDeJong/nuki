<?php

declare(strict_types=1);

namespace Darvis\Nuki\Concerns;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Facades\Auth;

/**
 * For a page only a main user may use: accounts with their API tokens, webhooks.
 *
 * Livewire runs the boot hook on the first render and on every later request, so one check
 * covers mount() and every public action. Without package users there is no main user; the
 * `viewNuki` gate on the routes guards the page then.
 */
trait AuthorizesMainUser
{
    public function bootAuthorizesMainUser(): void
    {
        if (! NukiConfig::authUsersEnabled()) {
            return;
        }

        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        if (! $user instanceof NukiUser || ! $user->isMain()) {
            abort(403);
        }
    }
}
