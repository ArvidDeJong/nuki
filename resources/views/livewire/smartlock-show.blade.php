<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('nuki.smartlocks.index') }}" icon="home" />
        <flux:breadcrumbs.item>{{ __('nuki::nuki.nav.smartlocks') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>#{{ $smartlockId }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.smartlocks.detail_info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.smartlocks.detail_info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.common.error') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->smartlock)
        @php
            $lock = $this->smartlock;
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

        @if ($lock->batteryCritical)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('nuki::nuki.smartlocks.battery_critical') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('nuki::nuki.smartlocks.battery_critical_text') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:card class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="xl">{{ $lock->name ?: '#'.$lock->smartlockId }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">ID {{ $lock->smartlockId }}</flux:text>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <flux:badge color="{{ $color }}" size="lg">
                        {{ $lock->stateName ?? __('nuki::nuki.smartlocks.state_unknown') }}
                    </flux:badge>
                    @if ($doorLabel)
                        <flux:badge color="{{ $doorColor }}" size="sm" icon="{{ $lock->doorState === 3 ? 'arrow-top-right-on-square' : 'check' }}">
                            {{ __('nuki::nuki.smartlocks.door_label', ['state' => $doorLabel]) }}
                        </flux:badge>
                    @endif
                </div>
            </div>

            <flux:separator />

            <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.battery') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ $lock->batteryCharge !== null ? $lock->batteryCharge.'%' : '—' }}</div>
                    @if ($lock->batteryCritical)
                        <flux:badge color="red" size="sm">{{ __('nuki::nuki.smartlocks.battery_low') }}</flux:badge>
                    @elseif ($lock->batteryCharging)
                        <flux:badge color="emerald" size="sm">{{ __('nuki::nuki.smartlocks.battery_charging') }}</flux:badge>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.firmware') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ $lock->firmwareVersion ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.hardware') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ $lock->hardwareVersion ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('nuki::nuki.smartlocks.last_update') }}</div>
                    <div class="mt-1 text-sm">{{ $lock->updateDate?->diffForHumans() ?? '—' }}</div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button.group>
                    <flux:button variant="primary" icon="lock-closed" wire:click="lock" wire:loading.attr="disabled" wire:target="lock">
                        {{ __('nuki::nuki.smartlocks.lock') }}
                    </flux:button>
                    <flux:button variant="filled" icon="lock-open" wire:click="unlock" wire:loading.attr="disabled" wire:target="unlock">
                        {{ __('nuki::nuki.smartlocks.unlock') }}
                    </flux:button>
                    <flux:button variant="filled" icon="bolt"
                                 wire:click="lockAndGo"
                                 wire:loading.attr="disabled"
                                 wire:target="lockAndGo"
                                 title="{{ __('nuki::nuki.smartlocks.lock_and_go_title') }}">
                        {!! str_replace(' ', '&nbsp;', __('nuki::nuki.smartlocks.lock_and_go')) !!}
                    </flux:button>
                </flux:button.group>

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" size="sm" icon="arrow-path"
                                 wire:click="sync" wire:loading.attr="disabled" wire:target="sync"
                                 title="{{ __('nuki::nuki.smartlocks.sync_title') }}">
                        {{ __('nuki::nuki.smartlocks.sync') }}
                    </flux:button>
                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openRename">
                        {{ __('nuki::nuki.smartlocks.rename') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Tab strip — no flux:tabs (Pro) — own Tailwind segmented control --}}
        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button
                type="button"
                wire:click="setTab('logs')"
                class="rounded-md px-4 py-1.5 text-sm font-medium transition
                    {{ $tab === 'logs'
                        ? 'bg-zinc-100 text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white'
                        : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('nuki::nuki.smartlocks.tab_activity') }}
            </button>
            <button
                type="button"
                wire:click="setTab('auths')"
                class="rounded-md px-4 py-1.5 text-sm font-medium transition
                    {{ $tab === 'auths'
                        ? 'bg-zinc-100 text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white'
                        : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('nuki::nuki.smartlocks.tab_auths') }}
            </button>
        </div>

        @if ($tab === 'logs')
            <flux:card class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.logs.date') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.logs.name') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.logs.action') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.logs.source') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($this->logs as $log)
                                <tr class="text-sm">
                                    <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                                        {{ $log->date?->isoFormat('L LTS') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2">{{ $log->name ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        <flux:badge size="sm" color="zinc">{{ $log->action }}</flux:badge>
                                    </td>
                                    <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                                        {{ $log->source ?? $log->authType ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500">
                                        {{ __('nuki::nuki.smartlocks.logs.empty') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @else
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('nuki::nuki.smartlocks.auths.heading') }}</flux:heading>
                    <flux:button icon="plus" variant="primary" wire:click="openCreateAuth">
                        {{ __('nuki::nuki.smartlocks.auths.new') }}
                    </flux:button>
                </div>

                <flux:card class="overflow-hidden p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.auths.name') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.auths.type') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.auths.status') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('nuki::nuki.smartlocks.auths.last_active') }}</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($this->auths as $auth)
                                    <tr class="text-sm">
                                        <td class="px-4 py-2 font-medium">{{ $auth->name }}</td>
                                        <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">{{ $auth->type }}</td>
                                        <td class="px-4 py-2">
                                            @if ($auth->enabled)
                                                <flux:badge color="emerald" size="sm">{{ __('nuki::nuki.common.active') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">{{ __('nuki::nuki.common.inactive') }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $auth->lastActiveDate?->diffForHumans() ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <flux:dropdown align="end">
                                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" />
                                                <flux:menu>
                                                    <flux:menu.item icon="pencil-square"
                                                                    wire:click="openEditAuth('{{ $auth->id }}')">
                                                        {{ __('nuki::nuki.common.edit') }}
                                                    </flux:menu.item>
                                                    <flux:menu.item icon="trash" variant="danger"
                                                                    wire:click="deleteAuth('{{ $auth->id }}')"
                                                                    wire:confirm="{{ __('nuki::nuki.smartlocks.auths.delete_confirm') }}">
                                                        {{ __('nuki::nuki.common.delete') }}
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500">
                                            {{ __('nuki::nuki.smartlocks.auths.empty') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif
    @endif

    <flux:modal wire:model.self="showAuthModal" name="auth-modal" variant="flyout" class="max-w-md">
        <form wire:submit="saveAuth" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingAuthId ? __('nuki::nuki.smartlocks.auth_modal.edit') : __('nuki::nuki.smartlocks.auth_modal.new') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('nuki::nuki.smartlocks.auth_modal.description') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.smartlocks.auths.name') }}</flux:label>
                <flux:input wire:model="authName" placeholder="{{ __('nuki::nuki.smartlocks.auth_modal.name_placeholder') }}" />
                <flux:error name="authName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.smartlocks.auths.type') }}</flux:label>
                <flux:select wire:model="authType">
                    <flux:select.option value="0">{{ __('nuki::nuki.smartlocks.auth_modal.type_app_user') }}</flux:select.option>
                    <flux:select.option value="2">{{ __('nuki::nuki.smartlocks.auth_modal.type_fob') }}</flux:select.option>
                    <flux:select.option value="3">{{ __('nuki::nuki.smartlocks.auth_modal.type_keypad') }}</flux:select.option>
                    <flux:select.option value="13">{{ __('nuki::nuki.smartlocks.auth_modal.type_keypad_code') }}</flux:select.option>
                </flux:select>
                <flux:error name="authType" />
            </flux:field>

            <flux:field>
                <flux:label>Code</flux:label>
                <flux:description>{{ __('nuki::nuki.smartlocks.auth_modal.code_description') }}</flux:description>
                <flux:input wire:model="authCode" type="number" inputmode="numeric" placeholder="123456" />
                <flux:error name="authCode" />
            </flux:field>

            <flux:separator />

            <div>
                <flux:heading size="sm">{{ __('nuki::nuki.smartlocks.auth_modal.time_slot') }}</flux:heading>
                <flux:text class="mt-1 text-xs">
                    {{ __('nuki::nuki.smartlocks.auth_modal.time_slot_description') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('nuki::nuki.smartlocks.auth_modal.valid_from') }}</flux:label>
                    <flux:input wire:model="authAllowedFromDate" type="datetime-local" />
                    <flux:error name="authAllowedFromDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('nuki::nuki.smartlocks.auth_modal.valid_until') }}</flux:label>
                    <flux:input wire:model="authAllowedUntilDate" type="datetime-local" />
                    <flux:error name="authAllowedUntilDate" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.smartlocks.auth_modal.allowed_days') }}</flux:label>
                <flux:description>{{ __('nuki::nuki.smartlocks.auth_modal.allowed_days_description') }}</flux:description>

                <div class="mt-3 grid grid-cols-7 gap-2">
                    @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $key)
                        @php $selected = in_array($key, $authWeekDays, true); @endphp
                        <button type="button"
                                wire:click="toggleWeekDay('{{ $key }}')"
                                class="flex flex-col items-center justify-center rounded-lg border px-2 py-3 text-xs font-medium transition
                                    {{ $selected
                                        ? 'border-sky-500 bg-sky-500 text-white shadow-sm dark:border-sky-400 dark:bg-sky-500'
                                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
                            <span>{{ __('nuki::nuki.weekdays.short.' . $key) }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    <flux:button type="button" size="xs" variant="ghost"
                                 wire:click="setWeekDayPreset('all')">{{ __('nuki::nuki.smartlocks.auth_modal.preset_all') }}</flux:button>
                    <flux:button type="button" size="xs" variant="ghost"
                                 wire:click="setWeekDayPreset('weekdays')">{{ __('nuki::nuki.smartlocks.auth_modal.preset_weekdays') }}</flux:button>
                    <flux:button type="button" size="xs" variant="ghost"
                                 wire:click="setWeekDayPreset('weekend')">{{ __('nuki::nuki.smartlocks.auth_modal.preset_weekend') }}</flux:button>
                    <flux:button type="button" size="xs" variant="ghost"
                                 wire:click="setWeekDayPreset('none')">{{ __('nuki::nuki.smartlocks.auth_modal.preset_clear') }}</flux:button>
                </div>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$wire.set('showAuthModal', false)">
                    {{ __('nuki::nuki.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingAuthId ? __('nuki::nuki.common.save') : __('nuki::nuki.common.add') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showRenameModal" name="rename-modal" class="max-w-sm">
        <form wire:submit="saveRename" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('nuki::nuki.smartlocks.rename_modal.heading') }}</flux:heading>
                <flux:text class="mt-1">{{ __('nuki::nuki.smartlocks.rename_modal.description') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.smartlocks.auths.name') }}</flux:label>
                <flux:input wire:model="renameValue" autofocus />
                <flux:error name="renameValue" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$wire.set('showRenameModal', false)">
                    {{ __('nuki::nuki.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">{{ __('nuki::nuki.common.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
