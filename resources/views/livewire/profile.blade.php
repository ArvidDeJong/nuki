<div class="mx-auto w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('nuki::nuki.profile.heading') }}</flux:heading>
        <flux:subheading>{{ __('nuki::nuki.profile.subheading') }}</flux:subheading>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('nuki::nuki.profile.info.heading') }}</flux:callout.heading>
        <flux:callout.text>{{ __('nuki::nuki.profile.info.text') }}</flux:callout.text>
    </flux:callout>

    <flux:card>
        <flux:heading size="lg">{{ __('nuki::nuki.profile.account_details') }}</flux:heading>

        <form wire:submit="saveProfile" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.name') }}</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.email') }}</flux:label>
                <flux:input wire:model="email" type="email" required />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.language') }}</flux:label>
                <flux:select wire:model="locale">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ((array) config('nuki.ui.locales', []) as $code => $label)
                        <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="locale" />
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model="twoFactorEnabled" />
                <div>
                    <flux:label>{{ __('nuki::nuki.profile.two_factor_label') }}</flux:label>
                    <flux:description>{{ __('nuki::nuki.profile.two_factor_description') }}</flux:description>
                </div>
            </flux:field>

            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.common.save') }}</flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">{{ __('nuki::nuki.profile.change_password') }}</flux:heading>

        <form wire:submit="changePassword" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.current_password') }}</flux:label>
                <flux:input wire:model="currentPassword" type="password" autocomplete="current-password" required />
                <flux:error name="currentPassword" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.new_password') }}</flux:label>
                <flux:input wire:model="newPassword" type="password" autocomplete="new-password" required />
                <flux:error name="newPassword" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('nuki::nuki.profile.confirm_password') }}</flux:label>
                <flux:input wire:model="newPasswordConfirmation" type="password" autocomplete="new-password" required />
                <flux:error name="newPasswordConfirmation" />
            </flux:field>

            <flux:button type="submit" variant="primary">{{ __('nuki::nuki.profile.change_password') }}</flux:button>
        </form>
    </flux:card>
</div>
