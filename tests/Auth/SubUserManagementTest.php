<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\SubUserShow;
use Darvis\Nuki\Livewire\SubUsersIndex;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('nuki.token_resolver', 'database');

    Http::fake(['api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks())]);

    $user = fn (string $name, ?int $parentId = null) => NukiUser::create([
        'parent_id' => $parentId,
        'name' => $name,
        'email' => strtolower(str_replace(' ', '-', $name)).'@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->main = $user('Main');
    $this->sub = $user('Sub', $this->main->id);
    $this->otherMain = $user('Other main');
    $this->otherSub = $user('Other sub', $this->otherMain->id);

    $this->mine = NukiAccount::create(['account_key' => 'mine', 'name' => 'Mine', 'api_token' => 'token-mine', 'is_active' => true]);
    $this->theirs = NukiAccount::create(['account_key' => 'theirs', 'name' => 'Theirs', 'api_token' => 'token-theirs', 'is_active' => true]);

    $this->main->accounts()->attach($this->mine->id, ['role' => 'owner']);
    $this->otherMain->accounts()->attach($this->theirs->id, ['role' => 'owner']);

    $this->theirRow = NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->otherSub->id,
        'nuki_account_id' => $this->theirs->id,
        'smartlock_id' => 17000000005,
        'can_lock' => false,
        'can_unlock' => false,
        'is_active' => true,
    ]);

    $this->page = fn () => Livewire::actingAs($this->main, 'darvis-nuki')->test(SubUserShow::class, ['id' => $this->sub->id]);
});

it('offers a main user only their own accounts for a sub user', function () {
    expect(($this->page)()->instance()->accounts->pluck('account_key')->all())->toBe(['mine']);
});

it('lists the locks of an account of the main user', function () {
    expect(($this->page)()->instance()->smartlocksForAccount($this->mine->id))->toHaveCount(5);
});

it('refuses to list the locks of an account the main user is not attached to', function () {
    ($this->page)()->call('smartlocksForAccount', $this->theirs->id)->assertForbidden();

    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-theirs'));
});

it('refuses to give access on an account the main user is not attached to', function () {
    ($this->page)()
        ->call('openCreate')
        ->set('accountId', $this->theirs->id)
        ->set('smartlockId', 17000000001)
        ->call('save')
        ->assertForbidden();

    expect($this->sub->smartlockAccess()->count())->toBe(0);
});

it('gives access on an account of the main user', function () {
    ($this->page)()
        ->call('openCreate')
        ->set('accountId', $this->mine->id)
        ->set('smartlockId', 17000000001)
        ->call('save')
        ->assertHasNoErrors()
        ->assertOk();

    expect($this->sub->smartlockAccess()->count())->toBe(1);
});

it('does not let a main user rewrite the access row of somebody else\'s sub user', function () {
    ($this->page)()
        ->call('openCreate')
        ->set('editingAccessId', $this->theirRow->id)
        ->set('accountId', $this->mine->id)
        ->set('smartlockId', 17000000001)
        ->set('canUnlock', true)
        ->call('save');

    $row = $this->theirRow->fresh();

    expect($row->nuki_user_id)->toBe($this->otherSub->id)
        ->and($row->nuki_account_id)->toBe($this->theirs->id)
        ->and($row->can_unlock)->toBeFalse();
});

it('does not let a main user delete the access row of somebody else\'s sub user', function () {
    ($this->page)()->call('delete', $this->theirRow->id);

    expect($this->theirRow->fresh())->not->toBeNull();
});

it('does not open the page of somebody else\'s sub user', function () {
    Livewire::actingAs($this->main, 'darvis-nuki')
        ->test(SubUserShow::class, ['id' => $this->otherSub->id])
        ->assertNotFound();
});

it('does not let the browser point the page at another sub user', function () {
    ($this->page)()->set('id', $this->otherSub->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses every sub user action once the signed in user is a sub user', function (string $component, array $parameters, string $method, array $arguments) {
    $parameters = array_map(fn ($value) => $value === ':sub' ? $this->sub->id : $value, $parameters);

    $page = Livewire::actingAs($this->main, 'darvis-nuki')->test($component, $parameters);

    $this->actingAs($this->sub, 'darvis-nuki');

    $page->call($method, ...$arguments)->assertForbidden();

    expect(NukiUser::count())->toBe(4);
})->with([
    'index openCreate' => [SubUsersIndex::class, [], 'openCreate', []],
    'index save' => [SubUsersIndex::class, [], 'save', []],
    'index delete' => [SubUsersIndex::class, [], 'delete', [1]],
    'index toggleActive' => [SubUsersIndex::class, [], 'toggleActive', [1]],
    'show openCreate' => [SubUserShow::class, ['id' => ':sub'], 'openCreate', []],
    'show save' => [SubUserShow::class, ['id' => ':sub'], 'save', []],
    'show delete' => [SubUserShow::class, ['id' => ':sub'], 'delete', [1]],
]);
