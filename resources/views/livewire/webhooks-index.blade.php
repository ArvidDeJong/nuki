<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('nuki::nuki.webhooks.heading') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('nuki::nuki.webhooks.subheading') }}
            </flux:text>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openSubscribe">
            {{ __('nuki::nuki.webhooks.add') }}
        </flux:button>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.webhooks.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.webhooks.info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.common.error') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    @if (! config('nuki.webhook.enabled'))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.webhooks.disabled_heading') }}</flux:callout.heading>
            <flux:callout.text>
                {!! __('nuki::nuki.webhooks.disabled_text', [
                    'env' => '<code>NUKI_WEBHOOK_ENABLED=true</code>',
                    'file' => '<code>.env</code>',
                    'secret' => '<code>NUKI_WEBHOOK_SECRET</code>',
                ]) !!}
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.webhooks.col_callback') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.webhooks.col_events') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('nuki::nuki.webhooks.col_created') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->webhooks as $hook)
                        <tr class="text-sm">
                            <td class="px-4 py-2 font-mono text-xs break-all">{{ $hook->callbackUrl }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($hook->events as $event)
                                        <flux:badge size="sm" color="zinc">{{ $event }}</flux:badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                                {{ $hook->creationDate?->isoFormat('L LT') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="unsubscribe('{{ $hook->id }}')"
                                    wire:confirm="{{ __('nuki::nuki.webhooks.delete_confirm') }}"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500">
                                {{ __('nuki::nuki.webhooks.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:modal wire:model.self="showSubscribeModal" name="subscribe-modal" class="max-w-lg">
        <form wire:submit="subscribe" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('nuki::nuki.webhooks.modal_heading') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('nuki::nuki.webhooks.modal_description') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.webhooks.col_callback') }}</flux:label>
                <flux:input wire:model="callbackUrl" type="url" placeholder="{{ __('nuki::nuki.webhooks.callback_url_placeholder') }}" />
                <flux:error name="callbackUrl" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.webhooks.col_events') }}</flux:label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach (['DEVICE_STATUS', 'DEVICE_CONFIG', 'DEVICE_LOGS', 'ACCOUNT_USER'] as $event)
                        <label class="flex items-center gap-2">
                            <flux:checkbox wire:model="events" value="{{ $event }}" />
                            <span class="text-sm">{{ $event }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="events" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$wire.set('showSubscribeModal', false)">
                    {{ __('nuki::nuki.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('nuki::nuki.webhooks.register') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
