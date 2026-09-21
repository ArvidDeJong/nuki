<?php

declare(strict_types=1);

namespace Darvis\Nuki\Concerns;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Facades\Auth;

trait AuthorizesSmartlockAccess
{
    /**
     * Smartlock ids the current user may see within the given account. Null is the wildcard:
     * a main user, or package users switched off. A sub user always gets a list, an empty one
     * for an account that has no row.
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
            // No row, so no lock can have been assigned: a sub user sees nothing here, never everything.
            return $user->isMain() ? null : [];
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
        if (! NukiConfig::authUsersEnabled()) {
            return null;
        }

        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }
}
