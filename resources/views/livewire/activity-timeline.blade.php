@php
    use Darvis\Nuki\Support\LogPresenter;

    $locksById = $this->smartlocks->keyBy('smartlockId');
    $groups = $this->groupedLogs;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.activity.heading') }}</flux:heading>
            <flux:text class="mt-1">{{ __('nuki::nuki.activity.subheading') }}</flux:text>
        </div>

        <flux:button wire:click="refresh" icon="arrow-path" variant="ghost">
            {{ __('nuki::nuki.common.refresh') }}
        </flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.activity.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.activity.info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.activity.error_heading') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('nuki::nuki.activity.filter_smartlock') }}</flux:label>
                <flux:select wire:model.live="smartlockId">
                    <flux:select.option value="">{{ __('nuki::nuki.activity.filter_smartlock_all') }}</flux:select.option>
                    @foreach ($this->smartlocks as $lock)
                        <flux:select.option value="{{ $lock->smartlockId }}">
                            {{ $lock->name ?: '#' . $lock->smartlockId }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.activity.filter_period') }}</flux:label>
                <flux:select wire:model.live="days">
                    <flux:select.option value="1">{{ __('nuki::nuki.activity.period_24h') }}</flux:select.option>
                    <flux:select.option value="3">{{ __('nuki::nuki.activity.period_3d') }}</flux:select.option>
                    <flux:select.option value="7">{{ __('nuki::nuki.activity.period_7d') }}</flux:select.option>
                    <flux:select.option value="30">{{ __('nuki::nuki.activity.period_30d') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <div class="flex items-end">
                <flux:button variant="ghost" icon="x-mark" wire:click="clearFilter"
                             :disabled="$smartlockId === null">
                    {{ __('nuki::nuki.activity.clear_filter') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    @if (empty($groups))
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('nuki::nuki.activity.empty_heading') }}</flux:callout.heading>
            <flux:callout.text>{{ __('nuki::nuki.activity.empty_text') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-8">
        @foreach ($groups as $day => $group)
            <div>
                <div class="mb-3 flex items-center gap-3">
                    <flux:heading size="lg">{{ $group['label'] }}</flux:heading>
                    <flux:badge size="sm" color="zinc">{{ $group['entries']->count() }}</flux:badge>
                </div>

                <div class="relative space-y-4 border-s-2 border-zinc-200 ps-6 dark:border-zinc-800">
                    @foreach ($group['entries'] as $log)
                        @php
                            $p = LogPresenter::describe($log);
                            $lock = $locksById[$log->smartlockId] ?? null;
                        @endphp

                        <div class="relative">
                            <span class="absolute -start-[33px] mt-1 flex size-6 items-center justify-center rounded-full bg-{{ $p['color'] }}-100 ring-4 ring-white dark:bg-{{ $p['color'] }}-500/15 dark:ring-zinc-900">
                                <flux:icon name="{{ $p['icon'] }}" variant="micro"
                                           class="size-3.5 text-{{ $p['color'] }}-600 dark:text-{{ $p['color'] }}-300" />
                            </span>

                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $p['label'] }}
                                    </flux:text>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                        @if ($lock)
                                            <a href="{{ route('nuki.smartlocks.show', $lock->smartlockId) }}"
                                               class="underline-offset-2 hover:underline">{{ $lock->name ?: '#' . $lock->smartlockId }}</a> ·
                                        @endif
                                        {{ $log->name ?: __('nuki::nuki.common.unknown') }} · {{ LogPresenter::triggerLabel($log->trigger) }}
                                    </flux:text>
                                </div>
                                <flux:text class="shrink-0 text-xs text-zinc-500" title="{{ $log->date?->isoFormat('L LTS') }}">
                                    {{ $log->date?->isoFormat('HH:mm') ?? '—' }}
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
