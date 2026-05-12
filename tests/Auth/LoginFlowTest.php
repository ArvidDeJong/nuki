<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Auth\LoginOtpPage;
use Darvis\Nuki\Livewire\Auth\LoginPage;
use Darvis\Nuki\Mail\NukiLoginOtpMail;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserOtpCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('logs in directly when 2FA is disabled', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'hoofd@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => false,
        'is_active' => true,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('submit');

    Mail::assertNothingSent();
    expect(Auth::guard('darvis-nuki')->check())->toBeTrue();
});

it('sends an OTP mail when 2FA is enabled and completes login on valid code', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'hoofd2@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('submit');

    Mail::assertSent(NukiLoginOtpMail::class, fn (NukiLoginOtpMail $mail) => $mail->hasTo($user->email));
    expect(Auth::guard('darvis-nuki')->check())->toBeFalse();

    $record = NukiUserOtpCode::query()->where('nuki_user_id', $user->id)->latest('id')->first();
    expect($record)->not->toBeNull();

    // Plaintext is alleen in de Mail-callable, in een test pakken we hem uit Mail::sent.
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
    NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'hoofd3@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ]);

    Mail::fake();

    Livewire::test(LoginPage::class)
        ->set('email', 'hoofd3@example.test')
        ->set('password', 'wrong-one')
        ->call('submit')
        ->assertSet('error', 'The combination of email address and password is incorrect.');

    Mail::assertNothingSent();
});

it('rejects an expired OTP code', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'hoofd4@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ]);

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
