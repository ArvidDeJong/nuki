<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\DTOs\LogEntry;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class ActivityTimeline extends Component
{
    use UsesNukiAccount;

    #[Url(as: 'lock')]
    public ?int $smartlockId = null;

    #[Url(as: 'days')]
    public int $days = 7;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.activity-timeline')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
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
            return Nuki::as($this->accountKey)->smartlocks()->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    /**
     * @return Collection<int, LogEntry>
     */
    #[Computed]
    public function logs(): Collection
    {
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
        $this->accountKey = $accountKey;
        $this->error = null;
        $this->smartlockId = null;
        unset($this->logs, $this->smartlocks);
    }
}
