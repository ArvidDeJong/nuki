<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Auth\VerifyEmailNoticePage;
use Darvis\Nuki\Mail\NukiVerifyEmailMail;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function unverifiedUser(string $email = 'verify@example.test'): NukiUser
{
    return NukiUser::create([
        'name' => 'Hoofd',
        'email' => $email,
        'password' => 'secret123',
        'two_factor_enabled' => true,
        'is_active' => true,
    ]);
}

function verifyUrlFor(NukiUser $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute(
        'nuki.auth.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => $hash ?? sha1($user->email)],
    );
}

it('marks the email as verified via a valid signed link', function () {
    $user = unverifiedUser();

    $this->get(verifyUrlFor($user))
        ->assertRedirect(route('nuki.auth.login'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered hash with 403', function () {
    $user = unverifiedUser('tamper@example.test');

    $this->get(verifyUrlFor($user, 'not-the-right-hash'))
        ->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an unsigned link', function () {
    $user = unverifiedUser('unsigned@example.test');

    $this->get('/nuki/email/verify/'.$user->id.'/'.sha1($user->email))
        ->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification mail from the notice page', function () {
    $user = unverifiedUser('resend@example.test');
    session(['nuki.pending_verification_user_id' => $user->id]);

    Mail::fake();

    Livewire::test(VerifyEmailNoticePage::class)
        ->call('resend')
        ->assertSet('info', 'We sent you a new confirmation email.');

    Mail::assertSent(NukiVerifyEmailMail::class, fn (NukiVerifyEmailMail $mail) => $mail->hasTo($user->email));
});

it('redirects away from the notice page without a pending user', function () {
    Livewire::test(VerifyEmailNoticePage::class)
        ->assertRedirect(route('nuki.auth.login'));
});
