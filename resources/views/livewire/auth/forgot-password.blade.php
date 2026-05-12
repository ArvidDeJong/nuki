<div>
    <flux:heading size="lg">{{ __('nuki::nuki.auth.forgot_heading') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('nuki::nuki.auth.forgot_subheading') }}</flux:subheading>

    @if ($info)
        <flux:callout variant="success" icon="check-circle" class="mt-6">{{ $info }}</flux:callout>
    @endif

    <form wire:submit="submit" class="mt-8 space-y-6">
        <flux:field>
            <flux:label>{{ __('nuki::nuki.auth.email') }}</flux:label>
            <flux:input wire:model="email" type="email" autocomplete="email" required autofocus />
            <flux:error name="email" />
        </flux:field>

        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.auth.send_link') }}</flux:button>
            <flux:link href="{{ route('nuki.auth.login') }}" variant="ghost">{{ __('nuki::nuki.auth.back_to_login') }}</flux:link>
        </div>
    </form>
</div>
