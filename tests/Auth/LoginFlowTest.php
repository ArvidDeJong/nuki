<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Auth\LoginOtpPage;
use Darvis\Nuki\Livewire\Auth\LoginPage;
use Darvis\Nuki\Mail\NukiLoginOtpMail;
use Darvis\Nuki\Mail\NukiVerifyEmailMail;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserOtpCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function makeVerifiedUser(array $overrides = []): NukiUser
{
    $user = NukiUser::create(array_merge([
        'name' => 'Hoofd',
        'email' => 'hoofd@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ], $overrides));

    $user->markEmailAsVerified();

    return $user->refresh();
}

it('blocks login for an unverified user and sends a verification mail', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'onbevestigd@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('submit');

    Mail::assertSent(NukiVerifyEmailMail::class, fn (NukiVerifyEmailMail $mail) => $mail->hasTo($user->email));
    Mail::assertNotSent(NukiLoginOtpMail::class);
    expect(Auth::guard('darvis-nuki')->check())->toBeFalse();
    expect(session('nuki.pending_verification_user_id'))->toBe($user->id);
});

it('logs in directly for a verified user when OTP is globally disabled', function () {
    config()->set('nuki.auth_users.otp.enabled', false);

    $user = makeVerifiedUser([
        'email' => 'hoofd@example.test',
        'two_factor_enabled' => false,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('submit');

    Mail::assertNothingSent();
    expect(Auth::guard('darvis-nuki')->check())->toBeTrue();
});

it('always requires OTP for a verified user even when two_factor_enabled is false', function () {
    $user = makeVerifiedUser([
        'email' => 'hoofd2@example.test',
        'two_factor_enabled' => false,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('submit');

    Mail::assertSent(NukiLoginOtpMail::class, fn (NukiLoginOtpMail $mail) => $mail->hasTo($user->email));
    expect(Auth::guard('darvis-nuki')->check())->toBeFalse();

    $plain = null;
    foreach (Mail::sent(NukiLoginOtpMail::class) as $mail) {
        $plain = $mail->code;
    }
    expect($plain)->not->toBeNull();

    Livewire::test(LoginOtpPage::class)
        ->set('code', $plain)
        ->call('submit');

    expect(Auth::guard('darvis-nuki')->check())->toBeTrue();
});

it('rejects a wrong password without sending mail', function () {
    makeVerifiedUser(['email' => 'hoofd3@example.test']);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', 'hoofd3@example.test')
        ->set('password', 'wrong-one')
        ->call('submit')
        ->assertSet('error', 'The combination of email address and password is incorrect.');

    Mail::assertNothingSent();
});

it('rejects an expired OTP code', function () {
    $user = makeVerifiedUser(['email' => 'hoofd4@example.test']);

    $plain = '123456';
    NukiUserOtpCode::create([
        'nuki_user_id' => $user->id,
        'code_hash' => Hash::make($plain),
        'purpose' => 'login',
        'expires_at' => now()->subMinute(),
    ]);

    session([
        'nuki.pending_otp_user_id' => $user->id,
        'nuki.pending_otp_remember' => false,
        'nuki.pending_otp_started_at' => now()->toIso8601String(),
    ]);

    Livewire::test(LoginOtpPage::class)
        ->set('code', $plain)
        ->call('submit')
        ->assertSet('error', 'The code is incorrect or expired.');

    expect(Auth::guard('darvis-nuki')->check())->toBeFalse();
});
