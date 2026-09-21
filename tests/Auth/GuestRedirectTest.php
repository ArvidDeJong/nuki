<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Middleware\AuthenticateNukiUser;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it('sends a guest of the package pages to the package login, without a host login route', function (string $path) {
    expect(Route::has('login'))->toBeFalse();

    $this->get($path)->assertRedirect(route('nuki.auth.login'));
})->with([
    'ui page' => ['/nuki/dashboard'],
    'ui root' => ['/nuki'],
    'profile' => ['/nuki/profile'],
    'sub users' => ['/nuki/sub-users'],
]);

it('sends a guest to the package login even when the host app has a login route', function () {
    Route::get('/host-login', fn () => 'login')->name('login');

    $this->get('/nuki/dashboard')->assertRedirect(route('nuki.auth.login'));
});

it('sends a signed in package user away from the guest pages to redirect_after_login', function (string $path) {
    config()->set('nuki.auth_users.redirect_after_login', '/nuki/activity');

    $user = NukiUser::create([
        'name' => 'Main',
        'email' => 'main@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->actingAs($user, 'darvis-nuki')->get($path)->assertRedirect('/nuki/activity');
})->with([
    'login' => ['/nuki/login'],
    'forgot password' => ['/nuki/password/forgot'],
]);

it('keeps the guard on the requests a page sends after it was loaded', function () {
    $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($persistent)->toContain(AuthenticateNukiUser::class);
});
