<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integration\Enums\IntegrationProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntegrationConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'scopes',
        'access_token_expires_at',
        'connected_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'provider' => IntegrationProvider::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'access_token_expires_at' => 'immutable_datetime',
        'connected_at' => 'immutable_datetime',
        'last_synced_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<IntegrationConnection> $query */
    public function scopeOwnedBy(
        Builder $query,
        User $user,
    ): void {
        $query->where(
            'user_id',
            $user->getKey(),
        );
    }
}
