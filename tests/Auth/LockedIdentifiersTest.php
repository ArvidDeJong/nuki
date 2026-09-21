<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\AccountSwitcher;
use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Livewire\OAuthConnect;
use Darvis\Nuki\Livewire\SmartlockShow;
use Darvis\Nuki\Livewire\SmartlocksIndex;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    // Tokens come from the nuki_accounts rows, so a named account can be called at all.
    config()->set('nuki.token_resolver', 'database');

    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock/*/log*' => Http::response(DemoFixtures::logsFor(17000000001)),
        'api.nuki.io/smartlock/*/auth*' => Http::response(DemoFixtures::authsFor(17000000001)),
        'api.nuki.io/smartlock/*' => Http::response(DemoFixtures::smartlocks()[0]),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
        'api.nuki.io/*' => Http::response([]),
    ]);

    $this->main = NukiUser::create([
        'name' => 'Main',
        'email' => 'main@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => false,
        'is_active' => true,
    ]);

    $this->sub = NukiUser::create([
        'parent_id' => $this->main->id,
        'name' => 'Sub',
        'email' => 'sub@example.test',
        'password' => 'secret123',
        'two_factor_enabled' => false,
        'is_active' => true,
    ]);

    $this->account = NukiAccount::create([
        'account_key' => 'customer-a',
        'name' => 'Customer A',
        'api_token' => 'token-a-secret',
        'is_active' => true,
    ]);

    // An account of somebody else: neither the main user nor the sub user is attached to it.
    $this->foreign = NukiAccount::create([
        'account_key' => 'customer-b',
        'name' => 'Customer B',
        'api_token' => 'token-b-secret',
        'is_active' => true,
    ]);

    $this->main->accounts()->attach($this->account->id, ['role' => 'owner']);

    // The sub user may operate one lock, and nothing else: no logs, no keypad codes.
    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->sub->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 17000000001,
        'can_lock' => true,
        'can_unlock' => true,
        'can_view_logs' => false,
        'can_manage_auths' => false,
        'is_active' => true,
    ]);

    session(['nuki.current_account' => 'customer-a']);
});

it('does not let the browser change the smartlock id', function () {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(SmartlockShow::class, ['smartlockId' => 17000000001])
        ->set('smartlockId', 200);
})->throws(CannotUpdateLockedPropertyException::class);

it('does not let the browser change the account key', function (string $component, array $parameters) {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test($component, $parameters)
        ->set('accountKey', 'customer-b');
})->with([
    'account switcher' => [AccountSwitcher::class, []],
    'activity timeline' => [ActivityTimeline::class, []],
    'dashboard' => [Dashboard::class, []],
    'oauth connect' => [OAuthConnect::class, []],
    'smartlock show' => [SmartlockShow::class, ['smartlockId' => 17000000001]],
    'smartlocks index' => [SmartlocksIndex::class, []],
])->throws(CannotUpdateLockedPropertyException::class);

it('keeps logs and keypad codes from a sub user without that permission', function (string $property) {
    $component = Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(SmartlockShow::class, ['smartlockId' => 17000000001])
        ->assertOk();

    expect(fn () => $component->instance()->{$property})->toThrow(
        fn (HttpException $e) => expect($e->getStatusCode())->toBe(403),
    );
})->with(['logs', 'auths']);

it('shows logs and keypad codes to a sub user with that permission', function () {
    NukiUserSmartlockAccess::query()->update(['can_view_logs' => true, 'can_manage_auths' => true]);

    $component = Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(SmartlockShow::class, ['smartlockId' => 17000000001])
        ->assertOk();

    expect($component->instance()->logs)->not->toBeEmpty()
        ->and($component->instance()->auths)->not->toBeEmpty();
});

it('refuses an account change event for an account the user has no access to', function (string $component, array $parameters) {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test($component, $parameters)
        ->dispatch('nuki-account-changed', accountKey: 'customer-b')
        ->assertForbidden();
})->with([
    'account switcher' => [AccountSwitcher::class, []],
    'activity timeline' => [ActivityTimeline::class, []],
    'dashboard' => [Dashboard::class, []],
    'oauth connect' => [OAuthConnect::class, []],
    'smartlock show' => [SmartlockShow::class, ['smartlockId' => 17000000001]],
    'smartlocks index' => [SmartlocksIndex::class, []],
]);

it('accepts an account change event for an accessible account', function () {
    session(['nuki.current_account' => 'default']);

    Livewire::actingAs($this->main, 'darvis-nuki')
        ->test(Dashboard::class)
        ->dispatch('nuki-account-changed', accountKey: 'customer-a')
        ->assertOk()
        ->assertSet('accountKey', 'customer-a');
});

it('refuses to switch to an account the user has no access to', function () {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(AccountSwitcher::class)
        ->call('switchTo', 'customer-b')
        ->assertForbidden();

    expect(session('nuki.current_account'))->toBe('customer-a');
});

it('lists only accessible accounts in the switcher for a package user', function () {
    $component = Livewire::actingAs($this->sub, 'darvis-nuki')->test(AccountSwitcher::class);

    expect($component->instance()->accounts->pluck('account_key')->all())->toBe(['customer-a']);

    expect($component->instance()->nukiName('customer-b'))->toBeNull();
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-b-secret'));
});

it('switches to an accessible account and to the default account', function () {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test(AccountSwitcher::class)
        ->call('switchTo', 'default')
        ->assertOk()
        ->assertSet('accountKey', 'default')
        ->call('switchTo', 'customer-a')
        ->assertOk()
        ->assertSet('accountKey', 'customer-a');

    expect(session('nuki.current_account'))->toBe('customer-a');
});

it('shows no locks to a sub user on an account without a row', function () {
    // Nobody is attached to an account, so the UI falls back to the `default` key, which has no row.
    $this->main->accounts()->detach();
    session()->forget('nuki.current_account');

    $component = Livewire::actingAs($this->sub, 'darvis-nuki')->test(SmartlocksIndex::class);

    expect($component->instance()->accountKey)->toBe('default')
        ->and($component->instance()->smartlocks)->toBeEmpty();
});

it('still shows every lock to a main user on an account without a row', function () {
    $this->main->accounts()->detach();
    session()->forget('nuki.current_account');

    $component = Livewire::actingAs($this->main, 'darvis-nuki')->test(SmartlocksIndex::class);

    expect($component->instance()->smartlocks)->toHaveCount(5);
});
