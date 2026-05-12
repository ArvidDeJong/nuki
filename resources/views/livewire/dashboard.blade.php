@php
    use Darvis\Nuki\Support\LogPresenter;
@endphp

<div class="space-y-8 py-8 md:py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.dashboard.heading') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('nuki::nuki.dashboard.subheading') }}
            </flux:text>
        </div>

        <flux:button wire:click="refresh" icon="arrow-path" variant="ghost">
            {{ __('nuki::nuki.common.refresh') }}
        </flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.dashboard.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.dashboard.info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.dashboard.error_heading') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    @php
        $t = $this->totals;
        $cards = [
            [
                'label' => __('nuki::nuki.dashboard.card_smartlocks'),
                'value' => $t['total'],
                'icon' => 'lock-closed',
                'color' => 'text-zinc-900 dark:text-white',
                'hint' => $t['total'] === 1
                    ? __('nuki::nuki.dashboard.device_one')
                    : __('nuki::nuki.dashboard.device_many', ['count' => $t['total']]),
            ],
            [
                'label' => __('nuki::nuki.dashboard.card_locked'),
                'value' => $t['locked'],
                'icon' => 'shield-check',
                'color' => 'text-sky-600 dark:text-sky-400',
                'hint' => __('nuki::nuki.dashboard.unlocked_count', ['count' => $t['unlocked']]),
            ],
            [
                'label' => __('nuki::nuki.dashboard.card_battery_critical'),
                'value' => $t['batteryCritical'],
                'icon' => 'battery-50',
                'color' => $t['batteryCritical'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400',
                'hint' => $t['averageBattery'] !== null
                    ? __('nuki::nuki.dashboard.avg_battery', ['percent' => round((float) $t['averageBattery'])])
                    : '—',
            ],
            [
                'label' => __('nuki::nuki.dashboard.card_doors_open'),
                'value' => $t['doorsOpen'],
                'icon' => 'arrow-top-right-on-square',
                'color' => $t['doorsOpen'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600 dark:text-zinc-400',
                'hint' => $t['doorsOpen'] > 0
                    ? __('nuki::nuki.dashboard.check_on_site')
                    : __('nuki::nuki.dashboard.all_closed'),
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            <flux:card class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">
                        {{ $card['label'] }}
                    </flux:text>
                    <flux:icon name="{{ $card['icon'] }}" variant="outline" class="size-5 text-zinc-400" />
                </div>
                <div class="text-3xl font-semibold {{ $card['color'] }}">{{ $card['value'] }}</div>
                <flux:text class="text-xs text-zinc-500">{{ $card['hint'] }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('nuki::nuki.dashboard.recent_activity') }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="arrow-right"
                             href="{{ route('nuki.activity.index') }}">
                    {{ __('nuki::nuki.dashboard.view_all') }}
                </flux:button>
            </div>

            @php
                $locksById = $this->smartlocks->keyBy('smartlockId');
            @endphp

            <div class="space-y-3">
                @forelse ($this->recentLogs as $log)
                    @php $p = LogPresenter::describe($log); @endphp
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-{{ $p['color'] }}-100 text-{{ $p['color'] }}-700 dark:bg-{{ $p['color'] }}-500/15 dark:text-{{ $p['color'] }}-300">
                            <flux:icon name="{{ $p['icon'] }}" variant="micro" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <flux:text class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $p['label'] }} — {{ $locksById[$log->smartlockId]->name ?? '#' . $log->smartlockId }}
                                </flux:text>
                                <flux:text class="text-xs text-zinc-500">
                                    {{ $log->date?->diffForHumans() ?? '—' }}
                                </flux:text>
                            </div>
                            <flux:text class="text-xs text-zinc-500">
                                {{ $log->name ?: __('nuki::nuki.common.unknown') }} · {{ LogPresenter::triggerLabel($log->trigger) }}
                            </flux:text>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('nuki::nuki.dashboard.no_activity') }}</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('nuki::nuki.dashboard.battery_overview') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500">
                    {{ __('nuki::nuki.dashboard.battery_thresholds') }}
                </flux:text>
            </div>

            <div class="space-y-3">
                @forelse ($this->smartlocks as $lock)
                    @php
                        $charge = $lock->batteryCharge ?? 0;
                        [$barClass, $badgeColor, $badgeKey] = match (true) {
                            $lock->batteryCritical => ['bg-rose-500', 'red', 'critical'],
                            $charge < 25 => ['bg-rose-500', 'red', 'low'],
                            $charge < 50 => ['bg-amber-500', 'amber', 'medium'],
                            default => ['bg-emerald-500', 'emerald', 'ok'],
                        };
                        $badgeLabel = __('nuki::nuki.battery.' . $badgeKey);
                    @endphp

                    <a href="{{ route('nuki.smartlocks.show', $lock->smartlockId) }}"
                       class="block rounded-lg p-2 transition hover:bg-zinc-100/60 dark:hover:bg-zinc-800/60">
                        <div class="flex items-center justify-between gap-3">
                            <flux:text class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $lock->name ?: '#' . $lock->smartlockId }}
                            </flux:text>
                            <div class="flex items-center gap-2 text-sm">
                                <span class="tabular-nums">{{ $lock->batteryCharge ?? '—' }}%</span>
                                @if ($lock->batteryCharging)
                                    <flux:badge color="emerald" size="sm" icon="bolt">{{ __('nuki::nuki.smartlocks.battery_charging') }}</flux:badge>
                                @else
                                    <flux:badge color="{{ $badgeColor }}" size="sm">{{ $badgeLabel }}</flux:badge>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                            <div class="h-full {{ $barClass }} transition-all"
                                 style="width: {{ max(2, min(100, $charge)) }}%"></div>
                        </div>
                    </a>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('nuki::nuki.dashboard.no_smartlocks') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
