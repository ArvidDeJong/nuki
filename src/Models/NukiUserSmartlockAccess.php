<?php

declare(strict_types=1);

namespace Darvis\Nuki\Models;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Support\WeekdayBitmask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NukiUserSmartlockAccess extends Model
{
    public const PERMISSIONS = ['lock', 'unlock', 'view_logs', 'manage_auths'];

    protected $table = 'nuki_user_smartlock';

    protected $fillable = [
        'nuki_user_id',
        'nuki_account_id',
        'smartlock_id',
        'can_lock',
        'can_unlock',
        'can_view_logs',
        'can_manage_auths',
        'allowed_from',
        'allowed_until',
        'allowed_weekdays',
        'is_active',
    ];

    protected $casts = [
        'smartlock_id' => 'integer',
        'can_lock' => 'boolean',
        'can_unlock' => 'boolean',
        'can_view_logs' => 'boolean',
        'can_manage_auths' => 'boolean',
        'allowed_from' => 'datetime',
        'allowed_until' => 'datetime',
        'allowed_weekdays' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NukiUser::class, 'nuki_user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(NukiAccount::class, 'nuki_account_id');
    }

    public function isCurrentlyAllowed(?CarbonImmutable $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        if ($this->allowed_from !== null && $now->lt($this->allowed_from)) {
            return false;
        }

        if ($this->allowed_until !== null && $now->gt($this->allowed_until)) {
            return false;
        }

        if ($this->allowed_weekdays !== null && $this->allowed_weekdays > 0) {
            if (! WeekdayBitmask::matchesDate($this->allowed_weekdays, $now)) {
                return false;
            }
        }

        return true;
    }

    public function hasPermission(string $permission): bool
    {
        if (! in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }

        return (bool) $this->{'can_'.$permission};
    }
}
