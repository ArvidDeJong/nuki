<?php

declare(strict_types=1);

use Darvis\Nuki\DTOs\SmartLock;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Support\Facades\Http;

it('lists smartlocks', function () {
    Http::fake([
        'api.nuki.io/smartlock' => Http::response([
            [
                'smartlockId' => 123,
                'accountId' => 1,
                'type' => 0,
                'name' => 'Voordeur',
                'state' => ['state' => 1, 'stateName' => 'locked', 'batteryCharge' => 80],
            ],
        ]),
    ]);

    $locks = Nuki::smartlocks()->all();

    expect($locks)->toHaveCount(1)
        ->and($locks->first())->toBeInstanceOf(SmartLock::class)
        ->and($locks->first()->smartlockId)->toBe(123)
        ->and($locks->first()->name)->toBe('Voordeur')
        ->and($locks->first()->stateName)->toBe('locked');
});

it('sends a lock action', function () {
    Http::fake([
        'api.nuki.io/smartlock/123/action' => Http::response('', 204),
    ]);

    Nuki::smartlocks()->lock(123);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.nuki.io/smartlock/123/action'
            && $request['action'] === 2;
    });
});

it('sends an unlock action', function () {
    Http::fake([
        'api.nuki.io/smartlock/123/action' => Http::response('', 204),
    ]);

    Nuki::smartlocks()->unlock(123);

    Http::assertSent(function ($request) {
        return $request['action'] === 1;
    });
});

it('renames a smartlock', function () {
    Http::fake([
        'api.nuki.io/smartlock/123' => Http::response('', 204),
    ]);

    Nuki::smartlocks()->update(123, ['name' => 'Voordeur kantoor']);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.nuki.io/smartlock/123'
            && $request['name'] === 'Voordeur kantoor';
    });
});

it('triggers a smartlock sync', function () {
    Http::fake([
        'api.nuki.io/smartlock/123/sync' => Http::response('', 204),
    ]);

    Nuki::smartlocks()->sync(123);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.nuki.io/smartlock/123/sync';
    });
});
