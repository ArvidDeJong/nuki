<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Livewire\WebhooksIndex;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake([
        'api.nuki.io/smartlock/log*' => Http::response(DemoFixtures::accountLogs()),
        'api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks()),
        'api.nuki.io/api/decentralWebhook*' => Http::response([]),
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

    $this->main->accounts()->attach($this->account->id, ['role' => 'owner']);
});

it('refuses a sub user on the accounts and webhooks pages', function (string $component) {
    Livewire::actingAs($this->sub, 'darvis-nuki')
        ->test($component)
        ->assertForbidden();
})->with([
    'accounts' => [AccountsIndex::class],
    'webhooks' => [WebhooksIndex::class],
]);

it('refuses a visitor without a package user on the accounts and webhooks pages', function (string $component) {
    Livewire::test($component)->assertForbidden();
})->with([
    'accounts' => [AccountsIndex::class],
    'webhooks' => [WebhooksIndex::class],
]);

it('opens the accounts and webhooks pages for a main user', function (string $component) {
    Livewire::actingAs($this->main, 'darvis-nuki')
        ->test($component)
        ->assertOk();
})->with([
    'accounts' => [AccountsIndex::class],
    'webhooks' => [WebhooksIndex::class],
]);

it('refuses every account action once the signed in user is a sub user', function (string $method, array $arguments) {
    $component = Livewire::actingAs($this->main, 'darvis-nuki')->test(AccountsIndex::class);

    $this->actingAs($this->sub, 'darvis-nuki');

    $arguments = array_map(fn ($argument) => $argument === ':id' ? $this->account->id : $argument, $arguments);

    $component->call($method, ...$arguments)->assertForbidden();

    expect($this->account->fresh())->not->toBeNull()
        ->and($this->account->fresh()->is_active)->toBeTrue();
})->with([
    'openCreate' => ['openCreate', []],
    'openEdit' => ['openEdit', [':id']],
    'save' => ['save', []],
    'testConnection' => ['testConnection', [':id']],
    'toggleActive' => ['toggleActive', [':id']],
    'delete' => ['delete', [':id']],
]);

it('refuses every webhook action once the signed in user is a sub user', function (string $method, array $arguments) {
    $component = Livewire::actingAs($this->main, 'darvis-nuki')->test(WebhooksIndex::class);

    $this->actingAs($this->sub, 'darvis-nuki');

    $component->call($method, ...$arguments)->assertForbidden();
})->with([
    'openSubscribe' => ['openSubscribe', []],
    'subscribe' => ['subscribe', []],
    'unsubscribe' => ['unsubscribe', ['12']],
]);

it('hides the accounts and webhooks links from a sub user', function () {
    $this->withoutVite();

    $this->actingAs($this->sub, 'darvis-nuki')
        ->get('/nuki/dashboard')
        ->assertOk()
        ->assertDontSee(route('nuki.accounts.index'))
        ->assertDontSee(route('nuki.webhooks.index'));

    $this->actingAs($this->main, 'darvis-nuki')
        ->get('/nuki/dashboard')
        ->assertOk()
        ->assertSee(route('nuki.accounts.index'))
        ->assertSee(route('nuki.webhooks.index'));
});
