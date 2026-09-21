<?php

declare(strict_types=1);

use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();

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
});

it('has no UI routes to link to', function () {
    expect(Route::has('nuki.dashboard'))->toBeFalse()
        ->and(Route::has('nuki.profile'))->toBeTrue();
});

it('renders the profile, sub users and sub user pages with the bundled UI switched off', function (string $path) {
    $path = str_replace(':sub', (string) $this->sub->id, $path);

    $this->actingAs($this->main, 'darvis-nuki')
        ->get($path)
        ->assertOk()
        ->assertSee('href="'.url('/nuki').'"', false);
})->with([
    'profile' => ['/nuki/profile'],
    'sub users' => ['/nuki/sub-users'],
    'one sub user' => ['/nuki/sub-users/:sub'],
]);
