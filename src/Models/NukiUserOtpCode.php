<?php

declare(strict_types=1);

namespace Darvis\Nuki\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class NukiUserOtpCode extends Model
{
    protected $table = 'nuki_user_otp_codes';

    protected $fillable = [
        'nuki_user_id',
        'code_hash',
        'purpose',
        'expires_at',
        'consumed_at',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NukiUser::class, 'nuki_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * Maakt een nieuwe code (plaintext) en het opgeslagen record. Eerdere
     * niet-gebruikte codes voor hetzelfde doel worden als verbruikt gemarkeerd
     * zodat altijd alleen de jongste code geldig is.
     *
     * @return array{0: self, 1: string}
     */
    public static function generate(
        NukiUser $user,
        string $purpose = 'login',
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $length = (int) config('nuki.auth_users.otp.length', 6);
        $expiryMinutes = (int) config('nuki.auth_users.otp.expiry_minutes', 5);

        $plain = self::randomNumericCode($length);

        // Invalidate alle oudere openstaande codes voor dit doel.
        static::query()
            ->where('nuki_user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $record = static::create([
            'nuki_user_id' => $user->id,
            'code_hash' => Hash::make($plain),
            'purpose' => $purpose,
            'expires_at' => CarbonImmutable::now()->addMinutes($expiryMinutes),
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
        ]);

        return [$record, $plain];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function matches(string $plain): bool
    {
        return Hash::check($plain, $this->code_hash);
    }

    public function consume(): void
    {
        $this->consumed_at = now();
        $this->save();
    }

    private static function randomNumericCode(int $length): string
    {
        $max = (10 ** $length) - 1;
        $code = (string) random_int(0, $max);

        return str_pad($code, $length, '0', STR_PAD_LEFT);
    }
}
