<div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">
                @if ($this->sub)
                    {{ $this->sub->name }}
                @endif
            </flux:heading>
            <flux:subheading>
                @if ($this->sub)
                    {{ __('nuki::nuki.sub_users.subheading_show', ['email' => $this->sub->email]) }}
                @endif
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="arrow-left" href="{{ route('nuki.sub-users.index') }}">
                {{ __('nuki::nuki.sub_users.back_to_list') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('nuki::nuki.sub_users.add_lock') }}</flux:button>
        </div>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.sub_users.detail_info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.sub_users.detail_info.text') }}</flux:callout.text>
    </flux:callout>

    <flux:card>
        @if ($this->accessRows->isEmpty())
            <div class="py-8 text-center text-sm text-zinc-500">
                {{ __('nuki::nuki.sub_users.empty_access') }}
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_account') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_lock') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_permissions') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_validity') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_days') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_status') }}</flux:table.column>
                    <flux:table.column>&nbsp;</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->accessRows as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row->account?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">#{{ $row->smartlock_id }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @if ($row->can_lock)<flux:badge size="sm" color="zinc">{{ __('nuki::nuki.sub_users.perm_lock') }}</flux:badge>@endif
                                    @if ($row->can_unlock)<flux:badge size="sm" color="zinc">{{ __('nuki::nuki.sub_users.perm_unlock') }}</flux:badge>@endif
                                    @if ($row->can_view_logs)<flux:badge size="sm" color="zinc">{{ __('nuki::nuki.sub_users.perm_view_logs') }}</flux:badge>@endif
                                    @if ($row->can_manage_auths)<flux:badge size="sm" color="zinc">{{ __('nuki::nuki.sub_users.perm_manage_auths') }}</flux:badge>@endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">
                                @if ($row->allowed_from || $row->allowed_until)
                                    {{ $row->allowed_from?->isoFormat('L LT') ?? '…' }}
                                    →
                                    {{ $row->allowed_until?->isoFormat('L LT') ?? '…' }}
                                @else
                                    {{ __('nuki::nuki.common.always') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">
                                @php($days = \Darvis\Nuki\Support\WeekdayBitmask::toDays($row->allowed_weekdays))
                                @if (empty($days))
                                    {{ __('nuki::nuki.common.all') }}
                                @else
                                    {{ implode(' ', array_map(fn ($d) => __('nuki::nuki.weekdays.short.' . $d), $days)) }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($row->is_active)
                                    <flux:badge color="lime" size="sm">{{ __('nuki::nuki.common.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('nuki::nuki.common.inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" variant="ghost" icon="pencil-square"
                                                 wire:click="openEdit({{ $row->id }})">
                                        {{ __('nuki::nuki.common.edit') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                                 wire:click="delete({{ $row->id }})"
                                                 wire:confirm="{{ __('nuki::nuki.sub_users.delete_access_confirm') }}">
                                        {{ __('nuki::nuki.sub_users.delete') }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal wire:model="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">
                {{ $editingAccessId ? __('nuki::nuki.sub_users.edit_access') : __('nuki::nuki.sub_users.new_access') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.sub_users.col_account') }}</flux:label>
                <flux:select wire:model.live="accountId">
                    <option value="">{{ __('nuki::nuki.sub_users.choose_account') }}</option>
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="accountId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.sub_users.smartlock_id') }}</flux:label>
                <flux:input wire:model="smartlockId" type="number" min="1" required />
                <flux:description>{{ __('nuki::nuki.sub_users.smartlock_id_description') }}</flux:description>
                <flux:error name="smartlockId" />
            </flux:field>

            <flux:fieldset>
                <flux:legend>{{ __('nuki::nuki.sub_users.permissions') }}</flux:legend>
                <div class="grid grid-cols-2 gap-2">
                    <flux:checkbox wire:model="canLock" label="{{ __('nuki::nuki.sub_users.perm_lock') }}" />
                    <flux:checkbox wire:model="canUnlock" label="{{ __('nuki::nuki.sub_users.perm_unlock') }}" />
                    <flux:checkbox wire:model="canViewLogs" label="{{ __('nuki::nuki.sub_users.perm_view_logs') }}" />
                    <flux:checkbox wire:model="canManageAuths" label="{{ __('nuki::nuki.sub_users.perm_manage_auths') }}" />
                </div>
            </flux:fieldset>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('nuki::nuki.smartlocks.auth_modal.valid_from') }}</flux:label>
                    <flux:input wire:model="allowedFrom" type="datetime-local" />
                    <flux:error name="allowedFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('nuki::nuki.smartlocks.auth_modal.valid_until') }}</flux:label>
                    <flux:input wire:model="allowedUntil" type="datetime-local" />
                    <flux:error name="allowedUntil" />
                </flux:field>
            </div>

            <flux:fieldset>
                <flux:legend>{{ __('nuki::nuki.sub_users.allowed_days') }}</flux:legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $val)
                        <flux:checkbox wire:model="weekdays" value="{{ $val }}" label="{{ __('nuki::nuki.weekdays.short.' . $val) }}" />
                    @endforeach
                </div>
                <flux:description>{{ __('nuki::nuki.sub_users.allowed_days_description') }}</flux:description>
            </flux:fieldset>

            <flux:field variant="inline">
                <flux:switch wire:model="isActive" />
                <flux:label>{{ __('nuki::nuki.sub_users.access_active') }}</flux:label>
            </flux:field>

            <div class="flex items-center justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('nuki::nuki.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('nuki::nuki.common.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
