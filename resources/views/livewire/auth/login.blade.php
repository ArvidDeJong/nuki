<div>
    <flux:heading size="lg">{{ __('nuki::nuki.auth.login_heading') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('nuki::nuki.auth.login_subheading', ['brand' => config('nuki.ui.brand', 'NUKI')]) }}</flux:subheading>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mt-6">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-6">
            {{ $error }}
        </flux:callout>
    @endif

    <form wire:submit="submit" class="mt-8 space-y-6">
        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.email') }}</flux:label>
            <flux:input wire:model="email" type="email" autocomplete="email" required autofocus />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.password') }}</flux:label>
            <flux:input wire:model="password" type="password" autocomplete="current-password" required />
            <flux:error name="password" />
        </flux:field>

        <flux:field variant="inline">
            <flux:checkbox wire:model="remember" />
            <flux:label>{{ __('nuki::nuki.auth.remember') }}</flux:label>
        </flux:field>

        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.auth.login') }}</flux:button>

            @if (config('nuki.auth_users.password_reset.enabled', true))
                <flux:link href="{{ route('nuki.auth.password.forgot') }}" variant="ghost">
                    {{ __('nuki::nuki.auth.forgot_password') }}
                </flux:link>
            @endif
        </div>

        @if (config('nuki.auth_users.register_enabled', true))
            <flux:separator class="my-6" />
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('nuki::nuki.auth.no_account') }}
                <flux:link href="{{ route('nuki.auth.register') }}">{{ __('nuki::nuki.auth.create_main_user') }}</flux:link>.
            </p>
        @endif
    </form>
</div>
