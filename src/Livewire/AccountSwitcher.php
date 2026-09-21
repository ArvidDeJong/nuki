<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountSwitcher extends Component
{
    use UsesNukiAccount;

    public function mount(): void
    {
        $this->mountUsesNukiAccount();
    }

    public function render(): View
    {
        return view('nuki::livewire.account-switcher');
    }

    #[Computed]
    public function accounts(): Collection
    {
        // Every active account without package users, the user's own accounts with them.
        return $this->getAvailableAccountsProperty();
    }

    /**
     * Whether the "manage accounts" link is shown: that page is for a main user only.
     */
    public function canManageAccounts(): bool
    {
        $user = $this->currentNukiUser();

        return $user === null || $user->isMain();
    }

    #[Computed]
    public function currentLabel(): string
    {
        if ($this->accountKey === 'default') {
            return (string) __('nuki::nuki.account_switcher.default');
        }

        $account = $this->accounts->firstWhere('account_key', $this->accountKey);

        return $account->name ?? $this->accountKey;
    }

    public function nukiName(string $accountKey): ?string
    {
        if (! $this->canUseAccountKey($accountKey)) {
            return null;
        }

        try {
            return Nuki::as($accountKey)->account()->info()?->displayName();
        } catch (\Throwable) {
            return null;
        }
    }

    public function switchTo(string $accountKey): void
    {
        $accountKey = $this->authorizedAccountKey($accountKey);

        $this->accountKey = $accountKey;
        session(['nuki.current_account' => $accountKey]);
        $this->dispatch('nuki-account-changed', accountKey: $accountKey);
    }

    #[On('nuki-account-changed')]
    public function syncAccount(string $accountKey): void
    {
        $this->accountKey = $this->authorizedAccountKey($accountKey);
        unset($this->currentLabel);
    }
}
