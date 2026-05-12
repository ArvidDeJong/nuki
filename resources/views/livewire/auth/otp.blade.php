<div>
    <flux:heading size="lg">{{ __('nuki::nuki.auth.otp_heading') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('nuki::nuki.auth.otp_subheading') }}</flux:subheading>

    @if ($info)
        <flux:callout variant="success" icon="check-circle" class="mt-6">{{ $info }}</flux:callout>
    @endif

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-6">{{ $error }}</flux:callout>
    @endif

    <form wire:submit="submit" class="mt-8 space-y-6">
        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.otp_code') }}</flux:label>
            <flux:input wire:model="code" autocomplete="one-time-code" inputmode="numeric"
                        pattern="[0-9]*" required autofocus />
            <flux:error name="code" />
        </flux:field>

        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.auth.confirm') }}</flux:button>
            <flux:button type="button" variant="ghost" wire:click="resend">
                {{ __('nuki::nuki.auth.resend') }}
            </flux:button>
        </div>

        <flux:separator class="my-6" />
        <flux:link href="{{ route('nuki.auth.login') }}" variant="ghost">
            {{ __('nuki::nuki.auth.back_to_login') }}
        </flux:link>
    </form>
</div>
