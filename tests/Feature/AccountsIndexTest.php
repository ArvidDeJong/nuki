<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Models\NukiAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('encrypts the api token when an account is created', function () {
    Livewire::test(AccountsIndex::class)
        ->call('openCreate')
        ->set('name', 'Office')
        ->set('accountKey', 'office')
        ->set('apiToken', 'first-secret-token')
        ->call('save')
        ->assertHasNoErrors();

    $stored = DB::table('nuki_accounts')->where('account_key', 'office')->value('api_token');

    expect($stored)->not->toContain('first-secret-token')
        ->and(NukiAccount::findByKey('office')->api_token)->toBe('first-secret-token');

    // Without package users there is nobody to attach.
    expect(DB::table('nuki_user_account')->count())->toBe(0);
});

it('encrypts the api token when an account is edited', function () {
    $account = NukiAccount::create([
        'name' => 'Office',
        'account_key' => 'office',
        'api_token' => 'first-secret-token',
        'is_active' => true,
    ]);

    Livewire::test(AccountsIndex::class)
        ->call('openEdit', $account->id)
        ->set('apiToken', 'second-secret-token')
        ->call('save')
        ->assertHasNoErrors();

    $stored = DB::table('nuki_accounts')->where('id', $account->id)->value('api_token');

    // A query builder update skips the encrypted cast: the token then sits in the table as plain
    // text, and reading it back throws a DecryptException.
    expect($stored)->not->toBe('second-secret-token')
        ->and($account->fresh()->api_token)->toBe('second-secret-token');
});

it('keeps the api token when an account is edited without a new one', function () {
    $account = NukiAccount::create([
        'name' => 'Office',
        'account_key' => 'office',
        'api_token' => 'first-secret-token',
        'is_active' => true,
    ]);

    Livewire::test(AccountsIndex::class)
        ->call('openEdit', $account->id)
        ->set('name', 'Head office')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->name)->toBe('Head office')
        ->and($account->fresh()->api_token)->toBe('first-secret-token');
});
