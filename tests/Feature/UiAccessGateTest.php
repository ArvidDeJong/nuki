<?php

declare(strict_types=1);

use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);
});

it('refuses the bundled UI outside the local environment when the host app defines no gate', function (string $path) {
    $this->get($path)->assertForbidden();
})->with([
    '/nuki',
    '/nuki/dashboard',
    '/nuki/activity',
    '/nuki/smartlocks/17000000001',
    '/nuki/webhooks',
    '/nuki/oauth/connect',
    '/nuki/accounts',
]);

it('opens the bundled UI in the local environment', function () {
    $this->app['env'] = 'local';

    $this->get('/nuki/dashboard')->assertOk();
});

it('opens the bundled UI when the host app gate allows it', function () {
    Gate::define('viewNuki', fn (?Authenticatable $user = null) => true);

    $this->get('/nuki/dashboard')->assertOk();
});

it('hands the signed in host user to the gate', function () {
    Gate::define('viewNuki', fn (?Authenticatable $user = null) => $user?->getAuthIdentifier() === 7);

    $this->get('/nuki/dashboard')->assertForbidden();

    $user = (new User)->forceFill(['id' => 7]);

    $this->actingAs($user)->get('/nuki/dashboard')->assertOk();
});

it('refuses the bundled UI when the host app gate denies it, also in the local environment', function () {
    $this->app['env'] = 'local';
    Gate::define('viewNuki', fn (?Authenticatable $user = null) => false);

    $this->get('/nuki/dashboard')->assertForbidden();
});
