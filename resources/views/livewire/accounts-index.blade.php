<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.accounts.heading') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('nuki::nuki.accounts.subheading') }}
            </flux:text>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openCreate">
            {{ __('nuki::nuki.accounts.new') }}
        </flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.accounts.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.accounts.info.text') }}</flux:callout.text>
    </flux:callout>

    @if (config('nuki.auth') !== 'token')
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>{{ __('nuki::nuki.accounts.warn_token_off_heading') }}</flux:callout.heading>
            <flux:callout.text>
                <code>NUKI_AUTH</code> {{ __('nuki::nuki.accounts.warn_token_off_text') }} <code>{{ config('nuki.auth') }}</code>. <code>NUKI_AUTH=token</code>.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if (config('nuki.token_resolver') !== 'database')
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>{{ __('nuki::nuki.accounts.warn_resolver_off_heading') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('nuki::nuki.accounts.warn_resolver_off_text') }} <code>NUKI_TOKEN_RESOLVER=database</code>
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.accounts.col_customer') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.accounts.col_key') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.accounts.col_status') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.accounts.col_token') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->accounts as $account)
                        <tr class="text-sm">
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ $account->name }}</div>
                                @if ($account->description)
                                    <div class="text-xs text-zinc-500">{{ $account->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $account->account_key }}</td>
                            <td class="px-4 py-2">
                                @if ($account->is_active)
                                    <flux:badge color="emerald" size="sm">{{ __('nuki::nuki.common.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('nuki::nuki.common.disabled') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if (filled($account->api_token))
                                    <flux:badge color="sky" size="sm" icon="key">{{ __('nuki::nuki.accounts.token_saved') }}</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">{{ __('nuki::nuki.accounts.token_missing') }}</flux:badge>
                                @endif

                                @if (isset($verification[$account->account_key]))
                                    @php($v = $verification[$account->account_key])
                                    <div class="mt-1 text-xs">
                                        @if ($v['status'] === 'ok')
                                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                <flux:icon name="check-circle" class="h-3.5 w-3.5" />
                                                {{ $v['message'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                                <flux:icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                                                {{ $v['message'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <flux:dropdown align="end">
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" />
                                    <flux:menu>
                                        <flux:menu.item icon="signal" wire:click="testConnection({{ $account->id }})">
                                            {{ __('nuki::nuki.accounts.test_connection') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $account->id }})">
                                            {{ __('nuki::nuki.common.edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="power" wire:click="toggleActive({{ $account->id }})">
                                            {{ $account->is_active ? __('nuki::nuki.accounts.deactivate') : __('nuki::nuki.accounts.activate') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                                        wire:click="delete({{ $account->id }})"
                                                        wire:confirm="{{ __('nuki::nuki.accounts.delete_confirm', ['key' => $account->account_key]) }}">
                                            {{ __('nuki::nuki.common.delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500">
                                {{ __('nuki::nuki.accounts.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:modal wire:model.self="showModal" name="account-modal" variant="flyout" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('nuki::nuki.accounts.edit_heading') : __('nuki::nuki.accounts.new_heading') }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ __('nuki::nuki.accounts.modal_description_before') }}
                    <a href="{{ config('nuki.web_url') }}" target="_blank" rel="noopener"
                       class="underline decoration-dotted hover:decoration-solid">
                        {{ config('nuki.web_url') }}
                    </a>
                    {{ __('nuki::nuki.accounts.modal_description_after') }} <code>API</code>.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.accounts.customer_name') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="name" placeholder="{{ __('nuki::nuki.accounts.customer_name_placeholder') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.accounts.account_key') }}</flux:label>
                <flux:description>
                    {{ __('nuki::nuki.accounts.account_key_description') }}
                </flux:description>
                <flux:input
                    wire:model="accountKey"
                    x-on:input="$wire.set('autoKey', false)"
                    placeholder="{{ __('nuki::nuki.accounts.account_key_placeholder') }}"
                />
                <flux:error name="accountKey" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.accounts.api_token') }}</flux:label>
                @if ($editingId)
                    <flux:description>{{ __('nuki::nuki.accounts.api_token_keep') }}</flux:description>
                @endif
                <flux:input wire:model="apiToken" type="password" autocomplete="off" placeholder="{{ __('nuki::nuki.accounts.api_token_placeholder') }}" />
                <flux:error name="apiToken" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.accounts.description') }}</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="{{ __('nuki::nuki.accounts.description_placeholder') }}" />
                <flux:error name="description" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="isActive" />
                <flux:label>{{ __('nuki::nuki.accounts.is_active') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$wire.set('showModal', false)">
                    {{ __('nuki::nuki.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('nuki::nuki.common.save') : __('nuki::nuki.common.add') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
