<?php

declare(strict_types=1);

use Darvis\Nuki\Http\Controllers\NukiLogoutController;
use Darvis\Nuki\Http\Controllers\NukiVerifyEmailController;
use Darvis\Nuki\Http\Middleware\SetLocale;
use Darvis\Nuki\Livewire\Auth\ForgotPasswordPage;
use Darvis\Nuki\Livewire\Auth\LoginOtpPage;
use Darvis\Nuki\Livewire\Auth\LoginPage;
use Darvis\Nuki\Livewire\Auth\RegisterPage;
use Darvis\Nuki\Livewire\Auth\ResetPasswordPage;
use Darvis\Nuki\Livewire\Auth\VerifyEmailNoticePage;
use Darvis\Nuki\Livewire\ProfilePage;
use Darvis\Nuki\Livewire\SubUserShow;
use Darvis\Nuki\Livewire\SubUsersIndex;
use Illuminate\Support\Facades\Route;

$config = config('nuki.auth_users');
$authMiddleware = array_values(array_unique(array_merge(
    $config['routes']['middleware'] ?? ['web'],
    [SetLocale::class],
)));

Route::middleware($authMiddleware)
    ->prefix($config['routes']['prefix'] ?? 'nuki')
    ->name('nuki.')
    ->group(function () use ($config) {
        Route::middleware('guest:darvis-nuki')->group(function () use ($config) {
            Route::get('/login', LoginPage::class)->name('auth.login');
            Route::get('/login/otp', LoginOtpPage::class)->name('auth.otp');

            if (($config['register_enabled'] ?? true) === true) {
                Route::get('/register', RegisterPage::class)->name('auth.register');
            }

            if (($config['password_reset']['enabled'] ?? true) === true) {
                Route::get('/password/forgot', ForgotPasswordPage::class)->name('auth.password.forgot');
                Route::get('/password/reset/{token}', ResetPasswordPage::class)->name('auth.password.reset');
            }

            if (($config['email_verification']['enabled'] ?? true) === true) {
                Route::get('/email/verify', VerifyEmailNoticePage::class)->name('auth.verify.notice');
                Route::get('/email/verify/{id}/{hash}', NukiVerifyEmailController::class)
                    ->middleware('signed')
                    ->whereNumber('id')
                    ->name('auth.verify');
            }
        });

        Route::middleware('auth:darvis-nuki')->group(function () {
            Route::post('/logout', NukiLogoutController::class)->name('auth.logout');
            Route::get('/profile', ProfilePage::class)->name('profile');
            Route::get('/sub-users', SubUsersIndex::class)->name('sub-users.index');
            Route::get('/sub-users/{id}', SubUserShow::class)
                ->whereNumber('id')
                ->name('sub-users.show');
        });
    });
