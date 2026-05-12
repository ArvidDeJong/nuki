<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth\Users;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Mail\NukiPasswordResetMail;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class NukiPasswordResetService
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function sendResetLink(string $email): bool
    {
        $user = NukiUser::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return false;
        }

        $token = Str::random(64);

        $this->db->table('nuki_password_resets')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token_hash' => Hash::make($token),
                'created_at' => CarbonImmutable::now(),
            ],
        );

        $expiryMinutes = (int) config('nuki.auth_users.password_reset.token_lifetime_minutes', 60);
        $url = route('nuki.auth.password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        Mail::to($user->email)->send(new NukiPasswordResetMail(
            resetUrl: $url,
            expiryMinutes: $expiryMinutes,
            recipientName: $user->name,
        ));

        return true;
    }

    public function findUserForToken(string $email, string $token): ?NukiUser
    {
        $row = $this->db->table('nuki_password_resets')->where('email', $email)->first();

        if ($row === null) {
            return null;
        }

        if (! Hash::check($token, $row->token_hash)) {
            return null;
        }

        $expiry = (int) config('nuki.auth_users.password_reset.token_lifetime_minutes', 60);
        if (CarbonImmutable::parse($row->created_at)->addMinutes($expiry)->isPast()) {
            return null;
        }

        return NukiUser::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();
    }

    public function reset(string $email, string $token, string $newPassword): bool
    {
        $user = $this->findUserForToken($email, $token);

        if ($user === null) {
            return false;
        }

        $user->password = $newPassword;
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->db->table('nuki_password_resets')->where('email', $email)->delete();

        return true;
    }
}
