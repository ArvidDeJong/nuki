<?php

declare(strict_types=1);

namespace Darvis\Nuki\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NukiAccount extends Model
{
    protected $table = 'nuki_accounts';

    protected $fillable = [
        'account_key',
        'name',
        'api_token',
        'description',
        'is_active',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(NukiUser::class, 'nuki_user_account')
            ->withPivot('role')
            ->withTimestamps();
    }

    public static function findByKey(string $accountKey): ?self
    {
        return static::query()->where('account_key', $accountKey)->first();
    }
}
