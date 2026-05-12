<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire\Auth;

use Darvis\Nuki\Auth\Users\NukiPasswordResetService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ForgotPasswordPage extends Component
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    public ?string $info = null;

    public function mount(): void
    {
        if (config('nuki.auth_users.password_reset.enabled', true) === false) {
            abort(404);
        }
    }

    public function render(): View
    {
        return view('nuki::livewire.auth.forgot-password')
            ->layout('nuki::layouts.auth');
    }

    public function submit(NukiPasswordResetService $service): void
    {
        $this->validate();
        $service->sendResetLink($this->email);

        // Lek geen account-bestaan: altijd dezelfde melding.
        $this->info = (string) __('nuki::nuki.auth.info.reset_link_sent');
        $this->email = '';
    }
}
