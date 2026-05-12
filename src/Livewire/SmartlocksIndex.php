<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\AuthorizesSmartlockAccess;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class SmartlocksIndex extends Component
{
    use AuthorizesSmartlockAccess;
    use UsesNukiAccount;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.smartlocks-index')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    #[Computed]
    public function smartlocks(): Collection
    {
        try {
            $all = Nuki::as($this->accountKey)->smartlocks()->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }

        $allowed = $this->userAccessibleSmartlockIds($this->accountKey);
        if ($allowed === null) {
            return $all;
        }

        return $all->filter(fn ($lock) => in_array((int) $lock->smartlockId, $allowed, true))->values();
    }

    public function canPerform(int $smartlockId, string $permission): bool
    {
        return $this->userCanAccessSmartlock($this->accountKey, $smartlockId, $permission);
    }

    public function lock(int $smartlockId): void
    {
        $this->assertCan($this->accountKey, $smartlockId, 'lock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->lock($smartlockId);
            session()->flash('status', __('nuki::nuki.flash.lock_sent_for', ['id' => $smartlockId]));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlocks);
    }

    public function unlock(int $smartlockId): void
    {
        $this->assertCan($this->accountKey, $smartlockId, 'unlock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->unlock($smartlockId);
            session()->flash('status', __('nuki::nuki.flash.unlock_sent_for', ['id' => $smartlockId]));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlocks);
    }

    public function lockAndGo(int $smartlockId): void
    {
        $this->assertCan($this->accountKey, $smartlockId, 'unlock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->lockAndGo($smartlockId);
            session()->flash('status', __('nuki::nuki.flash.lock_and_go_sent_for', ['id' => $smartlockId]));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlocks);
    }

    public function refresh(): void
    {
        unset($this->smartlocks);
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $accountKey;
        $this->error = null;
        unset($this->smartlocks);
    }
}
