<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire\Auth;

use Darvis\Nuki\Auth\Users\NukiPasswordResetService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ResetPasswordPage extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $error = null;

    public function mount(string $token, ?string $email = null): void
    {
        if (config('nuki.auth_users.password_reset.enabled', true) === false) {
            abort(404);
        }

        $this->token = $token;
        $this->email = $email ?? (string) request()->query('email', '');
    }

    public function render(): View
    {
        return view('nuki::livewire.auth.reset-password')
            ->layout('nuki::layouts.auth');
    }

    public function submit(NukiPasswordResetService $service): mixed
    {
        $this->error = null;
        $this->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string',
            'password' => 'required|string|min:8|max:255|confirmed:passwordConfirmation',
            'passwordConfirmation' => 'required|string|min:8|max:255',
        ]);

        $ok = $service->reset($this->email, $this->token, $this->password);

        if (! $ok) {
            $this->error = (string) __('nuki::nuki.auth.errors.reset_link_invalid');

            return null;
        }

        session()->flash('status', __('nuki::nuki.auth.info.password_changed'));

        return $this->redirect(route('nuki.auth.login'), navigate: false);
    }
}
