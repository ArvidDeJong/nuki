<?php

declare(strict_types=1);

use Darvis\Nuki\Auth\Users\NukiPasswordResetService;
use Darvis\Nuki\Mail\NukiPasswordResetMail;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('sends a reset mail and consumes the token after reset', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'reset@example.test',
        'password' => 'oldpass1',
        'is_active' => true,
    ]);

    Mail::fake();

    $service = app(NukiPasswordResetService::class);
    expect($service->sendResetLink($user->email))->toBeTrue();
    Mail::assertSent(NukiPasswordResetMail::class);

    // Manueel token reconstrueren via re-aanroep en hashes vergelijken kan niet — we genereren een nieuw token.
    // In plaats daarvan: simuleer een bekende token in de tabel.
    $token = 'manual-token-abc';
    DB::table('nuki_password_resets')->updateOrInsert(
        ['email' => $user->email],
        ['token_hash' => Hash::make($token), 'created_at' => now()],
    );

    expect($service->reset($user->email, $token, 'newpass99'))->toBeTrue();
    expect(Hash::check('newpass99', $user->fresh()->password))->toBeTrue();
    expect(DB::table('nuki_password_resets')->where('email', $user->email)->exists())->toBeFalse();
});

it('rejects an expired reset token', function () {
    $user = NukiUser::create([
        'name' => 'Hoofd',
        'email' => 'reset2@example.test',
        'password' => 'oldpass1',
        'is_active' => true,
    ]);

    $token = 'expired-token';
    DB::table('nuki_password_resets')->insert([
        'email' => $user->email,
        'token_hash' => Hash::make($token),
        'created_at' => now()->subHours(2),
    ]);

    $service = app(NukiPasswordResetService::class);
    expect($service->reset($user->email, $token, 'newpass99'))->toBeFalse();
});
