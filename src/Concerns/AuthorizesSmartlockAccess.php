<?php

declare(strict_types=1);

namespace Darvis\Nuki\Concerns;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Auth;

trait AuthorizesSmartlockAccess
{
    /**
     * Smartlock-ids waar de huidige gebruiker tot heeft binnen het opgegeven
     * account. Null = wildcard (hoofdgebruiker of auth_users uit).
     *
     * @return array<int, int>|null
     */
    protected function userAccessibleSmartlockIds(string $accountKey): ?array
    {
        $user = $this->currentNukiAuthUser();

        if ($user === null) {
            return null;
        }

        $accountId = NukiAccount::findByKey($accountKey)?->id;
        if ($accountId === null) {
            return null;
        }

        return $user->accessibleSmartlockIds($accountId);
    }

    protected function userCanAccessSmartlock(string $accountKey, int $smartlockId, string $permission): bool
    {
        $user = $this->currentNukiAuthUser();

        if ($user === null) {
            return true;
        }

        $accountId = NukiAccount::findByKey($accountKey)?->id;
        if ($accountId === null) {
            return $user->isMain();
        }

        return $user->canAccessSmartlock($accountId, $smartlockId, $permission);
    }

    protected function assertCan(string $accountKey, int $smartlockId, string $permission): void
    {
        if (! $this->userCanAccessSmartlock($accountKey, $smartlockId, $permission)) {
            abort(403);
        }
    }

    protected function currentNukiAuthUser(): ?NukiUser
    {
        if (config('nuki.auth_users.enabled') !== true) {
            return null;
        }

        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }
}
