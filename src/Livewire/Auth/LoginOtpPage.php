<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire\Auth;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Auth\Users\LoginThrottle;
use Darvis\Nuki\Mail\NukiLoginOtpMail;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserOtpCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LoginOtpPage extends Component
{
    #[Validate('required|string|min:4|max:10')]
    public string $code = '';

    public ?string $error = null;

    public ?string $info = null;

    public function mount(): void
    {
        if (! $this->hasPendingLogin()) {
            $this->redirect(route('nuki.auth.login'), navigate: false);
        }
    }

    public function render(): View
    {
        return view('nuki::livewire.auth.otp')
            ->layout('nuki::layouts.auth');
    }

    public function submit(LoginThrottle $throttle): mixed
    {
        $this->error = null;
        $this->info = null;
        $this->validate();

        $userId = (int) session('nuki.pending_otp_user_id', 0);
        if ($userId === 0 || ! $this->hasPendingLogin()) {
            $this->error = (string) __('nuki::nuki.auth.errors.session_expired');

            return $this->redirect(route('nuki.auth.login'), navigate: false);
        }

        if (! $throttle->checkAttempt($userId)) {
            $this->error = (string) __('nuki::nuki.auth.errors.too_many_attempts');

            return null;
        }

        $user = NukiUser::query()
            ->where('id', $userId)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            $this->clearPending();
            $this->error = (string) __('nuki::nuki.auth.errors.account_not_found');

            return $this->redirect(route('nuki.auth.login'), navigate: false);
        }

        $record = NukiUserOtpCode::query()
            ->where('nuki_user_id', $user->id)
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null || ! $record->matches(trim($this->code))) {
            $this->error = (string) __('nuki::nuki.auth.errors.code_invalid');

            return null;
        }

        $record->consume();
        $throttle->clearAttempts($user->id);

        $remember = (bool) session('nuki.pending_otp_remember', false);
        Auth::guard(AuthConfigRegistrar::GUARD)->login($user, $remember);
        $user->last_login_at = now();
        $user->save();

        $this->clearPending();

        return $this->redirect(
            (string) config('nuki.auth_users.redirect_after_login', '/nuki'),
            navigate: false,
        );
    }

    public function resend(LoginThrottle $throttle): void
    {
        $this->error = null;
        $this->info = null;

        $userId = (int) session('nuki.pending_otp_user_id', 0);
        $user = $userId > 0 ? NukiUser::find($userId) : null;
        if ($user === null) {
            $this->error = (string) __('nuki::nuki.auth.errors.session_expired');

            return;
        }

        if (! $throttle->checkSend($user->email, request()->ip())) {
            $this->error = (string) __('nuki::nuki.auth.errors.too_many_requests');

            return;
        }

        [$record, $plain] = NukiUserOtpCode::generate(
            $user,
            'login',
            request()->ip(),
            request()->userAgent(),
        );

        $mail = (new NukiLoginOtpMail(
            code: $plain,
            expiryMinutes: (int) config('nuki.auth_users.otp.expiry_minutes', 5),
            ip: request()->ip(),
            userAgent: request()->userAgent(),
            recipientName: $user->name,
        ))->locale((string) ($user->locale ?? config('nuki.ui.default_locale', config('app.locale', 'en'))));

        Mail::to($user->email)->send($mail);

        session(['nuki.pending_otp_code_id' => $record->id]);
        $this->info = (string) __('nuki::nuki.auth.info.new_code_sent');
    }

    private function hasPendingLogin(): bool
    {
        if (! session()->has('nuki.pending_otp_user_id')) {
            return false;
        }

        $startedAt = session('nuki.pending_otp_started_at');
        if ($startedAt === null) {
            return false;
        }

        return CarbonImmutable::parse($startedAt)->diffInMinutes(CarbonImmutable::now()) < 15;
    }

    private function clearPending(): void
    {
        session()->forget([
            'nuki.pending_otp_user_id',
            'nuki.pending_otp_remember',
            'nuki.pending_otp_started_at',
            'nuki.pending_otp_code_id',
        ]);
    }
}
