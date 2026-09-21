<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\AuthorizesSmartlockAccess;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\DTOs\LogEntry;
use Darvis\Nuki\DTOs\SmartLock;
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    use AuthorizesSmartlockAccess;
    use UsesNukiAccount;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.dashboard')
            ->layout(NukiConfig::uiLayout());
    }

    public function refresh(): void
    {
        unset($this->smartlocks, $this->recentLogs);
    }

    #[Computed]
    public function smartlocks(): Collection
    {
        try {
            $locks = Nuki::as($this->accountKey)->smartlocks()->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }

        // The totals are computed from this list, so a sub user's cards only count their own locks.
        $allowed = $this->userAccessibleSmartlockIds($this->accountKey);

        if ($allowed === null) {
            return $locks;
        }

        return $locks
            ->filter(fn (SmartLock $lock): bool => in_array($lock->smartlockId, $allowed, true))
            ->values();
    }

    #[Computed]
    public function recentLogs(): Collection
    {
        $readable = $this->userSmartlockIdsWithPermission($this->accountKey, 'view_logs');

        if ($readable === []) {
            return collect();
        }

        try {
            // The account wide log takes one lock id at most, so for a sub user a longer list is
            // fetched in the same single call and cut down here.
            $logs = Nuki::as($this->accountKey)->logs()->all(['limit' => $readable === null ? 8 : 100]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }

        if ($readable === null) {
            return $logs;
        }

        return $logs
            ->filter(fn (LogEntry $log): bool => in_array($log->smartlockId, $readable, true))
            ->take(8)
            ->values();
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
        $this->accountKey = $this->authorizedAccountKey($accountKey);
        $this->error = null;
        unset($this->smartlocks, $this->recentLogs);
    }
}
