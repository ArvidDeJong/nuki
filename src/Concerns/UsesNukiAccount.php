<?php

declare(strict_types=1);

namespace Darvis\Nuki\Concerns;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait UsesNukiAccount
{
    public string $accountKey = 'default';

    public function mountUsesNukiAccount(): void
    {
        $this->accountKey = $this->resolveAccountKey();
    }

    public static function resolveCurrentAccountKey(): string
    {
        return (string) session('nuki.current_account', 'default');
    }

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

        return $account?->name ?? $this->accountKey;
    }

    protected function currentNukiUser(): ?NukiUser
    {
        if (config('nuki.auth_users.enabled') !== true) {
            return null;
        }

        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
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
