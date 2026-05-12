<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('nuki::nuki.oauth.heading') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('nuki::nuki.oauth.description_before') }}
            <flux:badge size="sm" color="zinc">{{ $this->authMode }}</flux:badge>
        </flux:text>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.oauth.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.oauth.info.text') }}</flux:callout.text>
    </flux:callout>

    @if ($this->error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('nuki::nuki.common.error') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->error }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->authMode === 'token')
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('nuki::nuki.oauth.token_heading') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('nuki::nuki.oauth.token_description_before') }}
                    <a href="{{ config('nuki.web_url') }}" target="_blank" rel="noopener" class="underline">
                        {{ config('nuki.web_url') }}
                    </a>
                    {!! __('nuki::nuki.oauth.token_description_after', [
                        'env' => '<code>NUKI_API_TOKEN</code>',
                        'file' => '<code>.env</code>',
                    ]) !!}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.oauth.token_status') }}</flux:label>
                @if (filled(config('nuki.token')))
                    <flux:badge color="emerald">{{ __('nuki::nuki.oauth.configured') }}</flux:badge>
                @else
                    <flux:badge color="red">{{ __('nuki::nuki.oauth.missing') }}</flux:badge>
                @endif
            </flux:field>

            <flux:separator />

            <flux:text class="text-sm">
                {!! __('nuki::nuki.oauth.switch_to_oauth', ['env' => '<code>NUKI_AUTH=oauth</code>']) !!}
            </flux:text>
        </flux:card>
    @else
        <flux:card class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('nuki::nuki.oauth.oauth_heading') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('nuki::nuki.oauth.oauth_description', ['key' => $accountKey]) }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.oauth.account_key') }}</flux:label>
                <flux:description>
                    {{ __('nuki::nuki.oauth.account_key_description') }}
                </flux:description>
                <flux:input value="{{ $accountKey }}" readonly />
            </flux:field>

            @if ($this->tokenInfo)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-zinc-500">{{ __('nuki::nuki.oauth.expires_at') }}</div>
                        <div>{{ $this->tokenInfo['expires_at'] }}</div>

                        <div class="text-zinc-500">{{ __('nuki::nuki.oauth.token_status') }}</div>
                        <div>
                            @if ($this->tokenInfo['is_expired'])
                                <flux:badge color="red" size="sm">{{ __('nuki::nuki.oauth.expired') }}</flux:badge>
                            @else
                                <flux:badge color="emerald" size="sm">{{ __('nuki::nuki.oauth.token_active') }}</flux:badge>
                            @endif
                        </div>

                        <div class="text-zinc-500">{{ __('nuki::nuki.oauth.scope') }}</div>
                        <div class="break-all font-mono text-xs">{{ $this->tokenInfo['scope'] ?? '—' }}</div>
                    </div>
                </div>

                <flux:button variant="danger" icon="trash" wire:click="disconnect"
                             wire:confirm="{{ __('nuki::nuki.oauth.disconnect_confirm', ['key' => $accountKey]) }}">
                    {{ __('nuki::nuki.oauth.disconnect') }}
                </flux:button>
            @else
                <flux:callout icon="information-circle">
                    <flux:callout.heading>{{ __('nuki::nuki.oauth.not_connected_heading') }}</flux:callout.heading>
                    <flux:callout.text>
                        {!! __('nuki::nuki.oauth.not_connected_text', [
                            'param' => '<code>code</code>',
                            'cmd' => '<code>php artisan nuki:oauth-authorize</code>',
                        ]) !!}
                    </flux:callout.text>
                </flux:callout>

                <div class="space-y-3">
                    <flux:button wire:click="generateAuthorizationUrl" icon="link" variant="primary">
                        {{ __('nuki::nuki.oauth.generate_url') }}
                    </flux:button>

                    @if ($authorizeUrl)
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                            <flux:text class="mb-2 text-xs uppercase tracking-wide text-zinc-500">
                                {{ __('nuki::nuki.oauth.open_in_browser') }}
                            </flux:text>
                            <a href="{{ $authorizeUrl }}" target="_blank"
                               class="break-all font-mono text-xs text-blue-600 underline dark:text-blue-400">
                                {{ $authorizeUrl }}
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </flux:card>
    @endif
</div>
