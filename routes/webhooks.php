<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

$config = config('nuki.webhook');

Route::middleware($config['middleware'] ?? ['api'])
    ->post($config['route'] ?? '/nuki/webhook', WebhookController::class)
    ->name('nuki.webhook');
