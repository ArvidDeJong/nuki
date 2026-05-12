<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Auth\RegisterPage;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('creates a main user via the register page', function () {
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
    expect(Auth::guard('darvis-nuki')->check())->toBeTrue();
});

it('returns 404 when registration is disabled', function () {
    config()->set('nuki.auth_users.register_enabled', false);

    $this->get('/nuki/register')->assertNotFound();
});
