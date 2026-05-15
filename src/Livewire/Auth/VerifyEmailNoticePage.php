<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire\Auth;

use Darvis\Nuki\Auth\Users\LoginThrottle;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VerifyEmailNoticePage extends Component
{
    public ?string $info = null;

    public ?string $error = null;

    public string $email = '';

    public function mount(): mixed
    {
        if (config('nuki.auth_users.email_verification.enabled', true) === false) {
            abort(404);
        }

        $user = $this->pendingUser();

        if ($user === null) {
            return $this->redirect(route('nuki.auth.login'), navigate: false);
        }

        if ($user->hasVerifiedEmail()) {
            session()->forget('nuki.pending_verification_user_id');

            return $this->redirect(route('nuki.auth.login'), navigate: false);
        }

        $this->email = (string) $user->email;

        return null;
    }

    public function render(): View
    {
        return view('nuki::livewire.auth.verify-email')
            ->layout('nuki::layouts.auth');
    }

    public function resend(LoginThrottle $throttle): void
    {
        $this->error = null;
        $this->info = null;

        $user = $this->pendingUser();

        if ($user === null) {
            $this->error = (string) __('nuki::nuki.auth.errors.session_expired');

            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info = (string) __('nuki::nuki.auth.info.email_verified');

            return;
        }

        if (! $throttle->checkSend((string) $user->email, request()->ip())) {
            $this->error = (string) __('nuki::nuki.auth.errors.too_many_requests');

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->info = (string) __('nuki::nuki.auth.info.verification_sent');
    }

    private function pendingUser(): ?NukiUser
    {
        $id = session('nuki.pending_verification_user_id');

        if ($id === null) {
            return null;
        }

        return NukiUser::query()->find($id);
    }
}
