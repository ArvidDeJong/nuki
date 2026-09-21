<?php

declare(strict_types=1);

use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);
});

it('leaves the UI to the package guard when package users are on', function () {
    $user = NukiUser::create([
        'name' => 'Main',
        'email' => 'main@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => false,
        'is_active' => true,
    ]);

    // Outside local and without a viewNuki gate from the host app: the guard decides, not the gate.
    $this->actingAs($user, 'darvis-nuki')->get('/nuki/dashboard')->assertOk();
});

it('lets the package guard turn a guest away, not the viewNuki gate', function () {
    // A redirect to the package login, not the 403 the viewNuki gate would give outside local.
    $this->get('/nuki/dashboard')->assertRedirect(route('nuki.auth.login'));
});

it('keeps the login page reachable outside local', function () {
    $this->get('/nuki/login')->assertOk();
});
