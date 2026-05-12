<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire\Auth;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RegisterPage extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $error = null;

    public function mount(): void
    {
        if (config('nuki.auth_users.register_enabled', true) === false) {
            abort(404);
        }
    }

    public function render(): View
    {
        return view('nuki::livewire.auth.register')
            ->layout('nuki::layouts.auth');
    }

    public function submit(): mixed
    {
        $this->error = null;
        $validated = $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('nuki_users', 'email')],
            'password' => 'required|string|min:8|max:255|confirmed:passwordConfirmation',
            'passwordConfirmation' => 'required|string|min:8|max:255',
        ]);

        $user = NukiUser::create([
            'parent_id' => null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'two_factor_enabled' => true,
            'is_active' => true,
        ]);

        Auth::guard(AuthConfigRegistrar::GUARD)->login($user);

        return $this->redirect(
            (string) config('nuki.auth_users.redirect_after_login', '/nuki'),
            navigate: false,
        );
    }
}
