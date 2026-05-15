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

class LoginPage extends Component
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|min:6|max:255')]
    public string $password = '';

    public bool $remember = false;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.auth.login')
            ->layout('nuki::layouts.auth');
    }

    public function submit(LoginThrottle $throttle): mixed
    {
        $this->error = null;
        $this->validate();

        $user = NukiUser::query()
            ->where('email', $this->email)
            ->where('is_active', true)
            ->first();

        if ($user === null || ! Auth::guard(AuthConfigRegistrar::GUARD)->getProvider()->validateCredentials($user, ['password' => $this->password])) {
            $this->error = (string) __('nuki::nuki.auth.errors.invalid_credentials');

            return null;
        }

        // E-mailverificatie is verplicht: een onbevestigd account komt nooit
        // voorbij login. We sturen (gethrottled) een nieuwe link en gaan terug
        // naar de notice-pagina.
        if (config('nuki.auth_users.email_verification.enabled', true) === true && ! $user->hasVerifiedEmail()) {
            if ($throttle->checkSend($this->email, request()->ip())) {
                $user->sendEmailVerificationNotification();
            }

            session(['nuki.pending_verification_user_id' => $user->id]);

            return $this->redirect(route('nuki.auth.verify.notice'), navigate: false);
        }

        // OTP is verplicht voor iedereen zolang het globaal aanstaat — de
        // per-user `two_factor_enabled` kolom speelt hier bewust geen rol.
        if (config('nuki.auth_users.otp.enabled', true) === false) {
            return $this->completeLogin($user);
        }

        if (! $throttle->checkSend($this->email, request()->ip())) {
            $this->error = (string) __('nuki::nuki.auth.errors.too_many_requests');

            return null;
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

        session([
            'nuki.pending_otp_user_id' => $user->id,
            'nuki.pending_otp_remember' => $this->remember,
            'nuki.pending_otp_started_at' => CarbonImmutable::now()->toIso8601String(),
            'nuki.pending_otp_code_id' => $record->id,
        ]);

        return $this->redirect(route('nuki.auth.otp'), navigate: false);
    }

    private function completeLogin(NukiUser $user): mixed
    {
        Auth::guard(AuthConfigRegistrar::GUARD)->login($user, $this->remember);
        $user->last_login_at = now();
        $user->save();

        return $this->redirect(
            (string) config('nuki.auth_users.redirect_after_login', '/nuki'),
            navigate: false,
        );
    }
}
