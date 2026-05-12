<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\WeekdayBitmask;

beforeEach(function () {
    $this->main = NukiUser::create([
        'name' => 'Hoofd',
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
        'account_key' => 'klant-a',
        'name' => 'Klant A',
        'api_token' => 'token-a',
        'is_active' => true,
    ]);

    $this->main->accounts()->attach($this->account->id, ['role' => 'owner']);
});

it('main user has wildcard smartlock access', function () {
    expect($this->main->isMain())->toBeTrue();
    expect($this->main->accessibleSmartlockIds($this->account->id))->toBeNull();
    expect($this->main->canAccessSmartlock($this->account->id, 999, 'lock'))->toBeTrue();
});

it('sub user only sees assigned smartlocks', function () {
    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->sub->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 100,
        'can_lock' => true,
        'can_unlock' => true,
        'is_active' => true,
    ]);

    $ids = $this->sub->accessibleSmartlockIds($this->account->id);
    expect($ids)->toBe([100]);
    expect($this->sub->canAccessSmartlock($this->account->id, 100, 'lock'))->toBeTrue();
    expect($this->sub->canAccessSmartlock($this->account->id, 200, 'lock'))->toBeFalse();
});

it('sub user without permission cannot perform action', function () {
    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->sub->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 100,
        'can_lock' => false,
        'can_unlock' => true,
        'is_active' => true,
    ]);

    expect($this->sub->canAccessSmartlock($this->account->id, 100, 'lock'))->toBeFalse();
    expect($this->sub->canAccessSmartlock($this->account->id, 100, 'unlock'))->toBeTrue();
});

it('respects time window allowed_from/until', function () {
    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->sub->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 100,
        'can_lock' => true,
        'allowed_from' => now()->addDay(),
        'allowed_until' => now()->addDays(2),
        'is_active' => true,
    ]);

    expect($this->sub->canAccessSmartlock($this->account->id, 100, 'lock'))->toBeFalse();
});

it('respects weekday bitmask', function () {
    // Bitmask voor alleen "morgen" - bepaal dag.
    $tomorrow = CarbonImmutable::now()->addDay();
    // Geef alleen vandaag toestemming, niet morgen.
    $todayBit = (function () {
        $iso = CarbonImmutable::now()->dayOfWeekIso;
        $map = [1 => 64, 2 => 32, 3 => 16, 4 => 8, 5 => 4, 6 => 2, 7 => 1];

        return $map[$iso];
    })();

    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $this->sub->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 100,
        'can_lock' => true,
        'allowed_weekdays' => $todayBit,
        'is_active' => true,
    ]);

    expect($this->sub->canAccessSmartlock($this->account->id, 100, 'lock'))->toBeTrue();

    // En een rij die alleen 'morgen' toestaat moet falen.
    $tomorrowIso = $tomorrow->dayOfWeekIso;
    $map = [1 => 64, 2 => 32, 3 => 16, 4 => 8, 5 => 4, 6 => 2, 7 => 1];

    $sub2 = NukiUser::create([
        'parent_id' => $this->main->id,
        'name' => 'Sub 2',
        'email' => 'sub2@example.test',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    NukiUserSmartlockAccess::create([
        'nuki_user_id' => $sub2->id,
        'nuki_account_id' => $this->account->id,
        'smartlock_id' => 100,
        'can_lock' => true,
        'allowed_weekdays' => $map[$tomorrowIso],
        'is_active' => true,
    ]);

    expect($sub2->canAccessSmartlock($this->account->id, 100, 'lock'))->toBeFalse();
});

it('weekday bitmask helper round trips', function () {
    $days = ['ma', 'wo', 'vr'];
    $bitmask = WeekdayBitmask::fromDays($days);
    expect($bitmask)->toBe(64 | 16 | 4);
    expect(WeekdayBitmask::toDays($bitmask))->toBe($days);
});

it('sub inherits accounts from parent', function () {
    expect($this->sub->accessibleAccounts()->pluck('id')->all())
        ->toBe([$this->account->id]);
});
