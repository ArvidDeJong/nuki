<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const LOCK_WITH_LOGS = 17000000001;
const LOCK_WITHOUT_LOGS = 17000000002;
const LOCK_OF_NOBODY = 17000000003;

beforeEach(function () {
    config()->set('nuki.token_resolver', 'database');

    Http::fake([
        'api.nuki.io/smartlock/log*' => fn ($request) => Http::response(
            collect(DemoFixtures::accountLogs())
                ->when(
                    isset($request->data()['smartlockId']),
                    fn ($logs) => $logs->where('smartlockId', (int) $request->data()['smartlockId']),
                )
                ->take((int) ($request->data()['limit'] ?? 1000))
                ->values()
                ->all(),
        ),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
    ]);

    $this->main = NukiUser::create([
        'name' => 'Main',
        'email' => 'main@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->sub = NukiUser::create([
        'parent_id' => $this->main->id,
        'name' => 'Sub',
        'email' => 'sub@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->account = NukiAccount::create([
        'account_key' => 'customer-a',
        'name' => 'Customer A',
        'api_token' => 'token-a-secret',
        'is_active' => true,
    ]);

    $this->main->accounts()->attach($this->account->id, ['role' => 'owner']);

    // Two of the five locks: one with the log permission, one without.
    foreach ([LOCK_WITH_LOGS => true, LOCK_WITHOUT_LOGS => false] as $smartlockId => $viewLogs) {
        NukiUserSmartlockAccess::create([
            'nuki_user_id' => $this->sub->id,
            'nuki_account_id' => $this->account->id,
            'smartlock_id' => $smartlockId,
            'can_lock' => true,
            'can_unlock' => true,
            'can_view_logs' => $viewLogs,
            'is_active' => true,
        ]);
    }

    session(['nuki.current_account' => 'customer-a']);
});

it('counts and lists only the locks of a sub user on the dashboard', function () {
    $dashboard = Livewire::actingAs($this->sub, 'darvis-nuki')->test(Dashboard::class)->assertOk()->instance();

    expect($dashboard->smartlocks->pluck('smartlockId')->all())->toBe([LOCK_WITH_LOGS, LOCK_WITHOUT_LOGS])
        ->and($dashboard->totals['total'])->toBe(2);
});

it('shows a sub user only the recent activity of locks with the log permission', function () {
    $dashboard = Livewire::actingAs($this->sub, 'darvis-nuki')->test(Dashboard::class)->instance();

    expect($dashboard->recentLogs)->not->toBeEmpty()
        ->and($dashboard->recentLogs->pluck('smartlockId')->unique()->values()->all())->toBe([LOCK_WITH_LOGS]);
});

it('does not render the name of a lock the sub user has no access to', function () {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(Dashboard::class)
        ->assertSee('Voordeur Hoofdkantoor')
        ->assertDontSee('Magazijn Werkplaats');
});

it('shows a sub user only the timeline of locks with the log permission', function () {
    $timeline = Livewire::actingAs($this->sub, 'darvis-nuki')->test(ActivityTimeline::class)->assertOk()->instance();

    expect($timeline->logs)->not->toBeEmpty()
        ->and($timeline->logs->pluck('smartlockId')->unique()->values()->all())->toBe([LOCK_WITH_LOGS])
        ->and($timeline->smartlocks->pluck('smartlockId')->all())->toBe([LOCK_WITH_LOGS]);
});

it('refuses a timeline filter on a lock the sub user may not read', function (int $smartlockId) {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(ActivityTimeline::class)
        ->set('smartlockId', $smartlockId)
        ->assertForbidden();
})->with([
    'no access at all' => [LOCK_OF_NOBODY],
    'access without the log permission' => [LOCK_WITHOUT_LOGS],
]);

it('refuses the same filter when it comes in through the address bar', function () {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->withQueryParams(['lock' => LOCK_OF_NOBODY])
        ->test(ActivityTimeline::class)
        ->assertForbidden();
});

it('accepts a timeline filter on a lock the sub user may read', function () {
    $timeline = Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(ActivityTimeline::class)
        ->set('smartlockId', LOCK_WITH_LOGS)
        ->assertOk()
        ->instance();

    expect($timeline->logs->pluck('smartlockId')->unique()->values()->all())->toBe([LOCK_WITH_LOGS]);
});

it('keeps showing a main user everything', function () {
    $dashboard = Livewire::actingAs($this->main, 'darvis-nuki')->test(Dashboard::class)->instance();
    $timeline = Livewire::actingAs($this->main, 'darvis-nuki')
        ->test(ActivityTimeline::class)
        ->set('smartlockId', LOCK_OF_NOBODY)
        ->assertOk()
        ->instance();

    expect($dashboard->smartlocks)->toHaveCount(5)
        ->and($dashboard->totals['total'])->toBe(5)
        ->and($dashboard->recentLogs)->toHaveCount(8)
        ->and($timeline->smartlocks)->toHaveCount(5)
        ->and($timeline->logs->pluck('smartlockId')->unique()->values()->all())->toBe([LOCK_OF_NOBODY]);
});
