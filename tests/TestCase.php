<?php

declare(strict_types=1);

namespace Darvis\Nuki\Tests;

use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\NukiServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            NukiServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Nuki' => Nuki::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('nuki.auth', 'token');
        $app['config']->set('nuki.token_resolver', 'config');
        $app['config']->set('nuki.token', 'test-token');
        $app['config']->set('nuki.base_url', 'https://api.nuki.io');
        $app['config']->set('nuki.http.retries', 0);
        $app['config']->set('nuki.webhook.enabled', true);
        $app['config']->set('nuki.webhook.secret', 'webhook-secret');
        $app['config']->set('nuki.webhook.route', '/nuki/webhook');

        // Backwards-compat: bestaande tests draaien zonder user-auth.
        $app['config']->set('nuki.auth_users.enabled', false);
    }

    protected function enableAuthUsers($app): void
    {
        $app['config']->set('nuki.auth_users.enabled', true);
        $app['config']->set('nuki.auth_users.otp.expiry_minutes', 5);
        $app['config']->set('nuki.auth_users.otp.length', 6);
        $app['config']->set('nuki.auth_users.otp.rate_limit.max_per_window', 5);
        $app['config']->set('nuki.auth_users.otp.rate_limit.window_minutes', 15);

        $app['config']->set('auth.guards.darvis-nuki', [
            'driver' => 'session',
            'provider' => 'darvis-nuki-users',
        ]);
        $app['config']->set('auth.providers.darvis-nuki-users', [
            'driver' => 'eloquent',
            'model' => NukiUser::class,
        ]);
    }
}
