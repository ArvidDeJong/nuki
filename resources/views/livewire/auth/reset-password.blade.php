<div>
    <flux:heading size="lg">{{ __('nuki::nuki.auth.reset_heading') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('nuki::nuki.auth.reset_subheading') }}</flux:subheading>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-6">{{ $error }}</flux:callout>
    @endif

    <form wire:submit="submit" class="mt-8 space-y-6">
        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.email') }}</flux:label>
            <flux:input wire:model="email" type="email" autocomplete="email" required />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.new_password') }}</flux:label>
            <flux:input wire:model="password" type="password" autocomplete="new-password" required autofocus />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.confirm_new_password') }}</flux:label>
            <flux:input wire:model="passwordConfirmation" type="password" autocomplete="new-password" required />
            <flux:error name="passwordConfirmation" />
        </flux:field>

        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.auth.change_password') }}</flux:button>
            <flux:link href="{{ route('nuki.auth.login') }}" variant="ghost">{{ __('nuki::nuki.auth.back_to_login') }}</flux:link>
        </div>
    </form>
</div>
