<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Carbon\CarbonImmutable;
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
use Livewire\Attributes\Url;
use Livewire\Component;

class ActivityTimeline extends Component
{
    use AuthorizesSmartlockAccess;
    use UsesNukiAccount;

    #[Url(as: 'lock')]
    public ?int $smartlockId = null;

    #[Url(as: 'days')]
    public int $days = 7;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.activity-timeline')
            ->layout(NukiConfig::uiLayout());
    }

    public function refresh(): void
    {
        unset($this->logs, $this->smartlocks);
    }

    public function clearFilter(): void
    {
        $this->smartlockId = null;
        unset($this->logs);
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

        // The filter only offers locks whose log the user may read.
        $readable = $this->userSmartlockIdsWithPermission($this->accountKey, 'view_logs');

        if ($readable === null) {
            return $locks;
        }

        return $locks
            ->filter(fn (SmartLock $lock): bool => in_array($lock->smartlockId, $readable, true))
            ->values();
    }

    /**
     * @return Collection<int, LogEntry>
     */
    #[Computed]
    public function logs(): Collection
    {
        // The filter comes from the address bar, so it is checked, not trusted. Outside the try:
        // abort() throws, and the catch below would swallow it.
        if ($this->smartlockId !== null) {
            $this->assertCan($this->accountKey, $this->smartlockId, 'view_logs');
        }

        $readable = $this->userSmartlockIdsWithPermission($this->accountKey, 'view_logs');

        if ($readable === []) {
            return collect();
        }

        try {
            $filters = ['limit' => 100];
            if ($this->smartlockId !== null) {
                $filters['smartlockId'] = $this->smartlockId;
            }

            $logs = Nuki::as($this->accountKey)->logs()->all($filters);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }

        $cutoff = CarbonImmutable::now()->subDays(max(1, $this->days));

        return $logs
            ->filter(fn (LogEntry $log) => $log->date === null || $log->date->greaterThanOrEqualTo($cutoff))
            // One call for the whole account; a sub user's entries are picked out here.
            ->filter(fn (LogEntry $log) => $readable === null || in_array($log->smartlockId, $readable, true))
            ->values();
    }

    /**
     * Group logs by calendar day, newest day first.
     *
     * @return array<string, array{label: string, entries: Collection<int, LogEntry>}>
     */
    #[Computed]
    public function groupedLogs(): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();

        $groups = [];

        foreach ($this->logs as $log) {
            $date = $log->date?->startOfDay();
            $key = $date?->toDateString() ?? 'unknown';

            if (! isset($groups[$key])) {
                $label = match (true) {
                    $date === null => (string) __('nuki::nuki.activity.day_unknown'),
                    $date->equalTo($today) => (string) __('nuki::nuki.activity.day_today'),
                    $date->equalTo($yesterday) => (string) __('nuki::nuki.activity.day_yesterday'),
                    default => $date->translatedFormat('l j F'),
                };

                $groups[$key] = [
                    'label' => $label,
                    'entries' => collect(),
                ];
            }

            $groups[$key]['entries']->push($log);
        }

        return $groups;
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $this->authorizedAccountKey($accountKey);
        $this->error = null;
        $this->smartlockId = null;
        unset($this->logs, $this->smartlocks);
    }
}
