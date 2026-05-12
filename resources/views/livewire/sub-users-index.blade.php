<div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.sub_users.heading') }}</flux:heading>
            <flux:subheading>{{ __('nuki::nuki.sub_users.subheading') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('nuki::nuki.sub_users.new') }}</flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.sub_users.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.sub_users.info.text') }}</flux:callout.text>
    </flux:callout>

    <flux:card>
        @if ($this->subUsers->isEmpty())
            <div class="py-8 text-center text-sm text-zinc-500">
                {{ __('nuki::nuki.sub_users.empty') }}
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_name') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_email') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_2fa') }}</flux:table.column>
                    <flux:table.column>{{ __('nuki::nuki.sub_users.col_status') }}</flux:table.column>
                    <flux:table.column>&nbsp;</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->subUsers as $sub)
                        <flux:table.row>
                            <flux:table.cell>{{ $sub->name }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">{{ $sub->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($sub->two_factor_enabled)
                                    <flux:badge color="lime" size="sm">{{ __('nuki::nuki.common.enabled') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('nuki::nuki.common.disabled') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($sub->is_active)
                                    <flux:badge color="lime" size="sm">{{ __('nuki::nuki.common.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('nuki::nuki.common.inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" variant="ghost" icon="key"
                                                 href="{{ route('nuki.sub-users.show', ['id' => $sub->id]) }}">
                                        {{ __('nuki::nuki.sub_users.locks') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" icon="pencil-square"
                                                 wire:click="openEdit({{ $sub->id }})">
                                        {{ __('nuki::nuki.sub_users.edit') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" icon="power"
                                                 wire:click="toggleActive({{ $sub->id }})">
                                        {{ $sub->is_active ? __('nuki::nuki.sub_users.deactivate') : __('nuki::nuki.sub_users.activate') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                                 wire:click="delete({{ $sub->id }})"
                                                 wire:confirm="{{ __('nuki::nuki.sub_users.delete_confirm', ['email' => $sub->email]) }}">
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

    <flux:modal wire:model="showModal" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? __('nuki::nuki.sub_users.edit_heading') : __('nuki::nuki.sub_users.new_heading') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.sub_users.col_name') }}</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.sub_users.col_email') }}</flux:label>
                <flux:input wire:model="email" type="email" required />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ $editingId ? __('nuki::nuki.sub_users.password_keep') : __('nuki::nuki.sub_users.password') }}</flux:label>
                <flux:input wire:model="password" type="password" autocomplete="new-password"
                            :required="! $editingId" />
                <flux:error name="password" />
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model="twoFactorEnabled" />
                <flux:label>{{ __('nuki::nuki.sub_users.require_2fa') }}</flux:label>
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model="isActive" />
                <flux:label>{{ __('nuki::nuki.sub_users.account_active') }}</flux:label>
            </flux:field>

            <div class="flex items-center justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('nuki::nuki.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('nuki::nuki.common.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
