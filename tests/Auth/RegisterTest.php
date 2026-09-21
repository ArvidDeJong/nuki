<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Auth\RegisterPage;
use Darvis\Nuki\Mail\NukiVerifyEmailMail;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('creates an unverified main user and sends a verification mail', function () {
    config()->set('nuki.auth_users.register_enabled', true);
    Mail::fake();

    Livewire::test(RegisterPage::class)
        ->set('name', 'Nieuw')
        ->set('email', 'nieuw@example.test')
        ->set('password', 'wachtwoord1')
        ->set('passwordConfirmation', 'wachtwoord1')
        ->call('submit');

    $user = NukiUser::where('email', 'nieuw@example.test')->first();
    expect($user)->not->toBeNull();
    expect($user->parent_id)->toBeNull();
    expect($user->two_factor_enabled)->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeFalse();

    // Geen auto-login: e-mail moet eerst bevestigd worden.
    expect(Auth::guard('darvis-nuki')->check())->toBeFalse();
    expect(session('nuki.pending_verification_user_id'))->toBe($user->id);

    Mail::assertSent(NukiVerifyEmailMail::class, fn (NukiVerifyEmailMail $mail) => $mail->hasTo($user->email));
});

it('returns 404 when registration is disabled', function () {
    config()->set('nuki.auth_users.register_enabled', false);

    $this->get('/nuki/register')->assertNotFound();
});

it('keeps self registration off by default', function () {
    $this->withoutVite();

    expect(NukiConfig::registerEnabled())->toBeFalse();

    $this->get('/nuki/register')->assertNotFound();
    $this->get('/nuki/login')->assertOk()->assertDontSee('/nuki/register');
});

it('keeps self registration off when the config key is missing', function () {
    $config = config('nuki.auth_users');
    unset($config['register_enabled']);
    config()->set('nuki.auth_users', $config);

    expect(NukiConfig::registerEnabled())->toBeFalse();
});

it('refuses a registration that is submitted after registration was turned off', function () {
    config()->set('nuki.auth_users.register_enabled', true);
    Mail::fake();

    $component = Livewire::test(RegisterPage::class)
        ->set('name', 'Late')
        ->set('email', 'late@example.test')
        ->set('password', 'password-1')
        ->set('passwordConfirmation', 'password-1');

    config()->set('nuki.auth_users.register_enabled', false);

    $component->call('submit')->assertNotFound();

    expect(NukiUser::where('email', 'late@example.test')->exists())->toBeFalse();
});
