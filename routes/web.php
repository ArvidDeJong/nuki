<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Middleware\SetLocale;
use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Livewire\OAuthConnect;
use Darvis\Nuki\Livewire\SmartlockShow;
use Darvis\Nuki\Livewire\SmartlocksIndex;
use Darvis\Nuki\Livewire\WebhooksIndex;
use Illuminate\Support\Facades\Route;

$config = config('nuki.ui');
$middleware = $config['middleware'] ?? ['web'];

if (config('nuki.auth_users.enabled') === true) {
    $middleware = array_values(array_unique(array_merge($middleware, ['auth:darvis-nuki'])));
}

$middleware = array_values(array_unique(array_merge($middleware, [SetLocale::class])));

Route::middleware($middleware)
    ->prefix($config['prefix'] ?? 'nuki')
    ->name('nuki.')
    ->group(function () {
        Route::get('/', SmartlocksIndex::class)->name('smartlocks.index');
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/activity', ActivityTimeline::class)->name('activity.index');
        Route::get('/smartlocks/{smartlockId}', SmartlockShow::class)
            ->whereNumber('smartlockId')
            ->name('smartlocks.show');
        Route::get('/webhooks', WebhooksIndex::class)->name('webhooks.index');
        Route::get('/oauth/connect', OAuthConnect::class)->name('oauth.connect');
        Route::get('/accounts', AccountsIndex::class)->name('accounts.index');
    });
