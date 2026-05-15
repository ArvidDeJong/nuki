<div>
    <flux:heading size="lg">{{ __('nuki::nuki.auth.verify_heading') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('nuki::nuki.auth.verify_subheading', ['email' => $email]) }}</flux:subheading>

    @if ($info)
        <flux:callout variant="success" icon="check-circle" class="mt-6">{{ $info }}</flux:callout>
    @endif

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-6">{{ $error }}</flux:callout>
    @endif

    <div class="mt-8 flex items-center justify-between gap-3">
        <flux:button wire:click="resend" variant="primary">{{ __('nuki::nuki.auth.verify_resend') }}</flux:button>
        <flux:link href="{{ route('nuki.auth.login') }}" variant="ghost">{{ __('nuki::nuki.auth.verify_back_to_login') }}</flux:link>
    </div>
</div>
