<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);
});

it('renders dashboard KPI cards', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Dashboard')
        ->assertSee('Smartlocks')
        ->assertSee('Locked')
        ->assertSee('Critical battery')
        ->assertSee('Open doors');
});

it('computes correct totals from demo fixtures', function () {
    $component = Livewire::test(Dashboard::class);

    $totals = $component->instance()->totals;

    expect($totals['total'])->toBe(5)
        ->and($totals['locked'])->toBe(3) // smartlocks 1, 3, 5 zijn locked (state 1)
        ->and($totals['batteryCritical'])->toBe(1) // smartlock 5 heeft critical battery
        ->and($totals['doorsOpen'])->toBe(1); // smartlock 2 heeft doorState 3
});
