<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);
});

it('renders the timeline page', function () {
    Livewire::test(ActivityTimeline::class)
        ->assertSee('Activity')
        ->assertSee('Voordeur Hoofdkantoor');
});

it('groups logs by day with a Today label for fresh entries', function () {
    Livewire::test(ActivityTimeline::class)
        ->assertSee('Today');
});

it('passes the smartlockId filter to the API', function () {
    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response([]),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);

    Livewire::test(ActivityTimeline::class)
        ->set('smartlockId', 17000000005)
        ->call('refresh');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/smartlock/log')
            && str_contains($request->url(), 'smartlockId=17000000005');
    });
});

it('clears smartlock filter', function () {
    Livewire::test(ActivityTimeline::class)
        ->set('smartlockId', 17000000001)
        ->call('clearFilter')
        ->assertSet('smartlockId', null);
});
