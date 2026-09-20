<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Controllers\WebhookController;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Support\Facades\Route;

Route::middleware(NukiConfig::webhookMiddleware())
    ->post(NukiConfig::webhookRoute(), WebhookController::class)
    ->name('nuki.webhook');
