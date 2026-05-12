<?php

declare(strict_types=1);

use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Auth;

it('registers the darvis-nuki guard and provider without consumer config', function () {
    expect(config('auth.guards.darvis-nuki'))->toBe([
        'driver' => 'session',
        'provider' => 'darvis-nuki-users',
    ]);

    $guard = Auth::guard('darvis-nuki');

    expect($guard)->not->toBeNull();
    expect($guard->getProvider()->getModel())->toBe(NukiUser::class);
});
