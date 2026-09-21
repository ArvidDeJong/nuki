<?php

declare(strict_types=1);

namespace Darvis\Nuki\Concerns;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

trait UsesNukiAccount
{
    /**
     * Locked: the browser may read the key, never write it. It changes through mount() and the
     * `nuki-account-changed` event only, and both check the key against the user's accounts.
     */
    #[Locked]
    public string $accountKey = 'default';

    public function mountUsesNukiAccount(): void
    {
        $this->accountKey = $this->resolveAccountKey();
    }

    public static function resolveCurrentAccountKey(): string
    {
        return (string) session('nuki.current_account', 'default');
    }

    /**
     * Every active account without package users, the user's own accounts with them.
     *
     * @return Collection<int, NukiAccount>
     */
    public function getAvailableAccountsProperty(): Collection
    {
        $user = $this->currentNukiUser();

        if ($user === null) {
            return NukiAccount::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'account_key', 'name']);
        }

        return $user->accessibleAccounts()
            ->sortBy('name')
            ->values();
    }

    public function getCurrentAccountLabelProperty(): string
    {
        if ($this->accountKey === 'default') {
            return (string) __('nuki::nuki.account_switcher.default');
        }

        $account = NukiAccount::query()
            ->where('account_key', $this->accountKey)
            ->first(['name']);

        return $account->name ?? $this->accountKey;
    }

    protected function currentNukiUser(): ?NukiUser
    {
        if (! NukiConfig::authUsersEnabled()) {
            return null;
        }

        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }

    /**
     * Whether the current user may work in this account: `default`, or one of the user's
     * accessible accounts. Without package users every key is allowed, as it always was.
     */
    protected function canUseAccountKey(string $accountKey): bool
    {
        $user = $this->currentNukiUser();

        if ($user === null || $accountKey === 'default') {
            return true;
        }

        return $user->accessibleAccounts()->contains('account_key', $accountKey);
    }

    /**
     * The key itself when the current user may use it, a 403 otherwise. Every
     * `nuki-account-changed` handler goes through here, because the browser can send that event.
     */
    protected function authorizedAccountKey(string $accountKey): string
    {
        if (! $this->canUseAccountKey($accountKey)) {
            abort(403);
        }

        return $accountKey;
    }

    private function resolveAccountKey(): string
    {
        $key = self::resolveCurrentAccountKey();
        $user = $this->currentNukiUser();

        if ($user === null) {
            return $key;
        }

        $accessible = $user->accessibleAccounts();
        if ($accessible->isEmpty()) {
            return 'default';
        }

        if ($key !== 'default' && $accessible->contains('account_key', $key)) {
            return $key;
        }

        $first = (string) $accessible->first()->account_key;
        session(['nuki.current_account' => $first]);

        return $first;
    }
}
