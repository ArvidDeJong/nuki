<?php

declare(strict_types=1);

namespace Darvis\Nuki\Models;

use Darvis\Nuki\Mail\NukiPasswordResetMail;
use Darvis\Nuki\Mail\NukiVerifyEmailMail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class NukiUser extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'nuki_users';

    protected $fillable = [
        'parent_id',
        'name',
        'email',
        'password',
        'two_factor_enabled',
        'is_active',
        'last_login_at',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subUsers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(NukiAccount::class, 'nuki_user_account')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function smartlockAccess(): HasMany
    {
        return $this->hasMany(NukiUserSmartlockAccess::class);
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(NukiUserOtpCode::class);
    }

    public function isMain(): bool
    {
        return $this->parent_id === null;
    }

    public function isSub(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Accounts this user can use (sub erft van parent).
     */
    public function accessibleAccounts(): Collection
    {
        $own = $this->accounts()->where('is_active', true)->get();

        if ($this->isSub() && $this->parent !== null) {
            $parentAccounts = $this->parent->accounts()->where('is_active', true)->get();
            $own = $own->merge($parentAccounts);
        }

        return $own->unique('id')->values();
    }

    /**
     * Smartlock-ids waar deze user toe heeft binnen het opgegeven account.
     * Hoofdgebruiker: null = wildcard (alle sloten). Sub: array uit pivot.
     *
     * @return array<int, int>|null
     */
    public function accessibleSmartlockIds(int $nukiAccountId): ?array
    {
        if ($this->isMain()) {
            return null;
        }

        return $this->smartlockAccess()
            ->where('nuki_account_id', $nukiAccountId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (NukiUserSmartlockAccess $row) => $row->isCurrentlyAllowed())
            ->pluck('smartlock_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessSmartlock(int $nukiAccountId, int $smartlockId, string $permission): bool
    {
        if ($this->isMain()) {
            return true;
        }

        $row = $this->smartlockAccess()
            ->where('nuki_account_id', $nukiAccountId)
            ->where('smartlock_id', $smartlockId)
            ->where('is_active', true)
            ->first();

        if ($row === null) {
            return false;
        }

        return $row->isCurrentlyAllowed() && $row->hasPermission($permission);
    }

    public function sendPasswordResetNotification($token): void
    {
        $expiry = (int) config('nuki.auth_users.password_reset.token_lifetime_minutes', 60);
        $url = url(route('nuki.auth.password.reset', ['token' => $token, 'email' => $this->email], false));

        $mail = new NukiPasswordResetMail(
            resetUrl: $url,
            expiryMinutes: $expiry,
            recipientName: $this->name,
        );

        $locale = (string) ($this->locale ?? config('nuki.ui.default_locale', config('app.locale', 'en')));
        $mail->locale($locale);

        Mail::to($this->email)->send($mail);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = now();

        return $this->save();
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    public function sendEmailVerificationNotification(): void
    {
        $expiry = (int) config('nuki.auth_users.email_verification.link_lifetime_minutes', 60);

        $url = URL::temporarySignedRoute(
            'nuki.auth.verify',
            now()->addMinutes($expiry),
            ['id' => $this->id, 'hash' => sha1($this->getEmailForVerification())],
        );

        $mail = new NukiVerifyEmailMail(
            verifyUrl: $url,
            expiryMinutes: $expiry,
            recipientName: $this->name,
        );

        $locale = (string) ($this->locale ?? config('nuki.ui.default_locale', config('app.locale', 'en')));
        $mail->locale($locale);

        Mail::to($this->email)->send($mail);
    }
}
