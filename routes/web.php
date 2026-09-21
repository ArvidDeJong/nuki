<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Middleware\AuthorizeUi;
use Darvis\Nuki\Http\Middleware\SetLocale;
use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Livewire\OAuthConnect;
use Darvis\Nuki\Livewire\SmartlockShow;
use Darvis\Nuki\Livewire\SmartlocksIndex;
use Darvis\Nuki\Livewire\WebhooksIndex;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Facades\Route;

$middleware = NukiConfig::uiMiddleware();

if (NukiConfig::authUsersEnabled()) {
    $middleware[] = 'auth:darvis-nuki';
}

// After the host app's own middleware, so a session and a signed in user exist when the gate is asked.
$middleware[] = AuthorizeUi::class;
$middleware[] = SetLocale::class;
$middleware = array_values(array_unique($middleware));

Route::middleware($middleware)
    ->prefix(NukiConfig::uiPrefix())
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
