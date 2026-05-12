<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.smartlocks.heading') }}</flux:heading>
            <flux:text class="mt-1">{{ __('nuki::nuki.smartlocks.subheading') }}</flux:text>
        </div>

        <flux:button wire:click="refresh" icon="arrow-path" variant="ghost">
            {{ __('nuki::nuki.common.refresh') }}
        </flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.smartlocks.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.smartlocks.info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.smartlocks.error_heading') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->smartlocks->isEmpty() && ! $this->error)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('nuki::nuki.smartlocks.none_found_heading') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('nuki::nuki.smartlocks.none_found_text_before') }}
                <a class="underline" href="{{ route('nuki.oauth.connect') }}">{{ __('nuki::nuki.smartlocks.none_found_text_link') }}</a>{{ __('nuki::nuki.smartlocks.none_found_text_after') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->smartlocks as $lock)
            @php
                $stateName = strtolower((string) $lock->stateName);
                $color = match (true) {
                    str_contains($stateName, 'unlock') => 'lime',
                    str_contains($stateName, 'lock')   => 'sky',
                    str_contains($stateName, 'unlatch') => 'amber',
                    default => 'zinc',
                };
                $doorLabel = $lock->doorStateLabel();
                $doorColor = $lock->doorState === 3 ? 'amber' : ($lock->doorState === 2 ? 'emerald' : 'zinc');
            @endphp

            <flux:card @class([
                'space-y-4',
                'ring-2 ring-red-500/40' => $lock->batteryCritical,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="truncate">{{ $lock->name ?: '#'.$lock->smartlockId }}</flux:heading>
                        <flux:text class="text-xs text-zinc-500">ID {{ $lock->smartlockId }}</flux:text>
                    </div>

                    <div class="flex flex-col items-end gap-1">
                        <flux:badge color="{{ $color }}" size="sm">
                            {{ $lock->stateName ?? __('nuki::nuki.smartlocks.state_unknown') }}
                        </flux:badge>

                        @if ($doorLabel)
                            <flux:badge color="{{ $doorColor }}" size="sm" icon="{{ $lock->doorState === 3 ? 'arrow-top-right-on-square' : 'check' }}">
                                {{ __('nuki::nuki.smartlocks.door_label', ['state' => $doorLabel]) }}
                            </flux:badge>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.battery') }}</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span>{{ $lock->batteryCharge !== null ? $lock->batteryCharge.'%' : '—' }}</span>
                            @if ($lock->batteryCritical)
                                <flux:badge color="red" size="sm" icon="exclamation-triangle">{{ __('nuki::nuki.smartlocks.battery_low') }}</flux:badge>
                            @elseif ($lock->batteryCharging)
                                <flux:badge color="emerald" size="sm" icon="bolt">{{ __('nuki::nuki.smartlocks.battery_charging') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.firmware') }}</div>
                        <div class="mt-1">{{ $lock->firmwareVersion ?? '—' }}</div>
                    </div>
                </div>

                <flux:separator />

                <div class="flex items-center justify-between gap-2">
                    <flux:button.group>
                        <flux:button
                            size="sm"
                            variant="primary"
                            icon="lock-closed"
                            wire:click="lock({{ $lock->smartlockId }})"
                            wire:loading.attr="disabled"
                            wire:target="lock({{ $lock->smartlockId }})"
                        >
                            {{ __('nuki::nuki.smartlocks.lock') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="filled"
                            icon="lock-open"
                            wire:click="unlock({{ $lock->smartlockId }})"
                            wire:loading.attr="disabled"
                            wire:target="unlock({{ $lock->smartlockId }})"
                        >
                            {{ __('nuki::nuki.smartlocks.unlock') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="filled"
                            icon="bolt"
                            wire:click="lockAndGo({{ $lock->smartlockId }})"
                            wire:loading.attr="disabled"
                            wire:target="lockAndGo({{ $lock->smartlockId }})"
                            title="{{ __('nuki::nuki.smartlocks.lock_and_go_title') }}"
                        >
                            {!! str_replace(' ', '&nbsp;', __('nuki::nuki.smartlocks.lock_and_go')) !!}
                        </flux:button>
                    </flux:button.group>

                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="arrow-right"
                        href="{{ route('nuki.smartlocks.show', $lock->smartlockId) }}"
                    >
                        {{ __('nuki::nuki.smartlocks.details') }}
                    </flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
