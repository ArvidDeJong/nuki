<?php

declare(strict_types=1);

use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Livewire\AccountSwitcher;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->main = NukiUser::create([
        'name' => 'Main',
        'email' => 'main@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);
});

it('lets a main user without any account open the accounts page', function () {
    $this->withoutVite();

    expect($this->main->accessibleAccounts())->toBeEmpty();

    $this->actingAs($this->main, 'darvis-nuki')->get('/nuki/accounts')->assertOk();
});

it('attaches the main user who creates an account as its owner', function () {
    Livewire::actingAs($this->main, 'darvis-nuki')
        ->test(AccountsIndex::class)
        ->call('openCreate')
        ->set('name', 'Office')
        ->set('accountKey', 'office')
        ->set('apiToken', 'first-secret-token')
        ->call('save')
        ->assertHasNoErrors();

    $attached = $this->main->accounts()->get();

    expect($attached)->toHaveCount(1)
        ->and($attached->first()->account_key)->toBe('office')
        ->and($attached->first()->pivot->role)->toBe('owner');

    // And so the account is in that user's switcher straight away.
    $switcher = Livewire::actingAs($this->main, 'darvis-nuki')->test(AccountSwitcher::class);

    expect($switcher->instance()->accounts->pluck('account_key')->all())->toBe(['office']);
});

it('attaches nobody when an existing account is edited', function () {
    $other = NukiUser::create([
        'name' => 'Other main',
        'email' => 'other@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $account = NukiAccount::create([
        'account_key' => 'office',
        'name' => 'Office',
        'api_token' => 'first-secret-token',
        'is_active' => true,
    ]);

    $other->accounts()->attach($account->id, ['role' => 'owner']);

    Livewire::actingAs($this->main, 'darvis-nuki')
        ->test(AccountsIndex::class)
        ->call('openEdit', $account->id)
        ->set('name', 'Head office')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('nuki_user_account')->count())->toBe(1)
        ->and($this->main->accounts()->count())->toBe(0);
});

it('attaches a new main user to the accounts named on the command line', function () {
    NukiAccount::create(['account_key' => 'office', 'name' => 'Office', 'api_token' => 'token-office', 'is_active' => true]);
    NukiAccount::create(['account_key' => 'workshop', 'name' => 'Workshop', 'api_token' => 'token-workshop', 'is_active' => true]);

    $this->artisan('nuki:user-create', [
        '--email' => 'admin@example.test',
        '--name' => 'Admin',
        '--password' => 'secret123',
        '--account' => ['office', 'workshop'],
    ])->assertSuccessful();

    $user = NukiUser::where('email', 'admin@example.test')->firstOrFail();

    expect($user->accounts()->orderBy('account_key')->pluck('account_key')->all())->toBe(['office', 'workshop'])
        ->and($user->accounts()->first()->pivot->role)->toBe('owner');
});

it('creates nothing when an account key on the command line does not exist', function () {
    NukiAccount::create(['account_key' => 'office', 'name' => 'Office', 'api_token' => 'token-office', 'is_active' => true]);

    $this->artisan('nuki:user-create', [
        '--email' => 'admin@example.test',
        '--name' => 'Admin',
        '--password' => 'secret123',
        '--account' => ['office', 'nowhere'],
    ])
        ->expectsOutputToContain('nowhere')
        ->assertFailed();

    expect(NukiUser::where('email', 'admin@example.test')->exists())->toBeFalse()
        ->and(DB::table('nuki_user_account')->count())->toBe(0);
});

it('still creates a main user without any account option', function () {
    $this->artisan('nuki:user-create', [
        '--email' => 'admin@example.test',
        '--name' => 'Admin',
        '--password' => 'secret123',
    ])->assertSuccessful();

    expect(NukiUser::where('email', 'admin@example.test')->firstOrFail()->accounts()->count())->toBe(0);
});
