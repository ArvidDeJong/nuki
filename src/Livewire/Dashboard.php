<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\DTOs\SmartLock;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    use UsesNukiAccount;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.dashboard')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    public function refresh(): void
    {
        unset($this->smartlocks, $this->recentLogs);
    }

    #[Computed]
    public function smartlocks(): Collection
    {
        try {
            return Nuki::as($this->accountKey)->smartlocks()->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    #[Computed]
    public function recentLogs(): Collection
    {
        try {
            return Nuki::as($this->accountKey)->logs()->all(['limit' => 8]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    #[Computed]
    public function totals(): array
    {
        $locks = $this->smartlocks;

        return [
            'total' => $locks->count(),
            'locked' => $locks->filter(fn (SmartLock $l) => $l->isLocked())->count(),
            'unlocked' => $locks->filter(fn (SmartLock $l) => $l->isUnlocked())->count(),
            'batteryCritical' => $locks->filter(
                fn (SmartLock $l) => $l->batteryCritical === true
                    || $l->keypadBatteryCritical === true
                    || $l->doorsensorBatteryCritical === true
            )->count(),
            'doorsOpen' => $locks->filter(fn (SmartLock $l) => $l->doorState === 3)->count(),
            'averageBattery' => $locks->filter(fn (SmartLock $l) => $l->batteryCharge !== null)
                ->avg(fn (SmartLock $l) => $l->batteryCharge),
        ];
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $accountKey;
        $this->error = null;
        unset($this->smartlocks, $this->recentLogs);
    }
}
