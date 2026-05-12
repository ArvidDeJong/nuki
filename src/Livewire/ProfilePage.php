<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Component;

class ProfilePage extends Component
{
    public string $name = '';

    public string $email = '';

    public bool $twoFactorEnabled = true;

    public ?string $locale = null;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->twoFactorEnabled = (bool) $user->two_factor_enabled;
        $this->locale = $user->locale;
    }

    public function render(): View
    {
        return view('nuki::livewire.profile')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    public function saveProfile(): void
    {
        $user = $this->user();
        $allowedLocales = array_keys((array) config('nuki.ui.locales', ['en' => 'English']));

        $validated = $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('nuki_users', 'email')->ignore($user->id)],
            'twoFactorEnabled' => 'boolean',
            'locale' => ['nullable', 'string', new In($allowedLocales)],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->two_factor_enabled = (bool) $validated['twoFactorEnabled'];
        $user->locale = $validated['locale'] ?? null;
        $user->save();

        session()->flash('status', __('nuki::nuki.flash.profile_updated'));
    }

    public function changePassword(): void
    {
        $user = $this->user();

        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8|max:255|confirmed:newPasswordConfirmation',
            'newPasswordConfirmation' => 'required|string|min:8|max:255',
        ]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __('nuki::nuki.profile.current_password_incorrect'));

            return;
        }

        $user->password = $this->newPassword;
        $user->save();

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';

        session()->flash('status', __('nuki::nuki.flash.password_changed'));
    }

    private function user(): NukiUser
    {
        /** @var NukiUser $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }
}
