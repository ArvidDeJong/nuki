<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Models\NukiAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountSwitcher extends Component
{
    public string $accountKey = 'default';

    public function mount(): void
    {
        $this->accountKey = (string) session('nuki.current_account', 'default');
    }

    public function render(): View
    {
        return view('nuki::livewire.account-switcher');
    }

    #[Computed]
    public function accounts(): Collection
    {
        return NukiAccount::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['account_key', 'name']);
    }

    #[Computed]
    public function currentLabel(): string
    {
        if ($this->accountKey === 'default') {
            return (string) __('nuki::nuki.account_switcher.default');
        }

        $account = $this->accounts->firstWhere('account_key', $this->accountKey);

        return $account?->name ?? $this->accountKey;
    }

    public function nukiName(string $accountKey): ?string
    {
        try {
            return Nuki::as($accountKey)->account()->info()?->displayName();
        } catch (\Throwable) {
            return null;
        }
    }

    public function switchTo(string $accountKey): void
    {
        $this->accountKey = $accountKey;
        session(['nuki.current_account' => $accountKey]);
        $this->dispatch('nuki-account-changed', accountKey: $accountKey);
    }

    #[On('nuki-account-changed')]
    public function syncAccount(string $accountKey): void
    {
        $this->accountKey = $accountKey;
        unset($this->currentLabel);
    }
}
