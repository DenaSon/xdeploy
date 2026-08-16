<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Casts\ServerCredentialCast;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'host',
        'port',
        'username',
        'authentication_type',
        'credential',
        'pending_credential',
        'bootstrap_credential_rotated_at',
        'status',
        'cloud_provider',
        'cloud_server_id',
        'cloud_region',
        'provisioned_at',
        'expires_at',
        'termination_started_at',
        'termination_last_attempt_at',
        'termination_attempts',
        'termination_last_error',
        'terminated_at',
    ];

    protected $hidden = [
        'credential',
        'credential_context',
        'pending_credential',
        'pending_credential_context',
    ];

    protected $casts = [
        'port' => 'integer',
        'credential' => ServerCredentialCast::class,
        'pending_credential' => ServerCredentialCast::class,
        'status' => ServerStatus::class,
        'authentication_type' => AuthenticationType::class,
        'bootstrap_credential_rotated_at' => 'immutable_datetime',
        'provisioned_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'termination_started_at' => 'immutable_datetime',
        'termination_last_attempt_at' => 'immutable_datetime',
        'termination_attempts' => 'integer',
        'terminated_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    /** @return HasMany<PublicEndpoint, $this> */
    public function publicEndpoints(): HasMany
    {
        return $this->hasMany(
            PublicEndpoint::class,
        );
    }

    /** @return HasMany<SupportRequest, $this> */
    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function isActive(): bool
    {
        return $this->status === ServerStatus::Active;
    }

    public function isCloudProvisioned(): bool
    {
        return $this->cloud_provider !== null
            && $this->cloud_server_id !== null;
    }

    public function isUserProvided(): bool
    {
        return $this->cloud_provider === null
            && $this->cloud_server_id === null;
    }

    public function hasConnectionHost(): bool
    {
        return is_string($this->host)
            && trim($this->host) !== '';
    }

    public function hasCredential(): bool
    {
        return is_string($this->credential)
            && trim($this->credential) !== '';
    }

    public function hasPendingCredential(): bool
    {
        return is_string($this->pending_credential)
            && trim($this->pending_credential) !== '';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->lessThanOrEqualTo(
                now(),
            );
    }

    public function isTerminated(): bool
    {
        return $this->terminated_at !== null;
    }

    public function scopeActive(
        Builder $query,
    ): void {
        $query->where(
            'status',
            ServerStatus::Active,
        );
    }

    public function scopeOwnedBy(
        Builder $query,
        User $user,
    ): void {
        $query->where(
            'user_id',
            $user->getKey(),
        );
    }

    public function scopeActiveFor(
        Builder $query,
        User $user,
    ): void {
        $query
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->where(
                'status',
                ServerStatus::Active,
            );
    }

    public function scopeExpiredCloud(
        Builder $query,
    ): void {
        $query
            ->whereNotNull(
                'cloud_provider',
            )
            ->whereNotNull(
                'cloud_server_id',
            )
            ->whereNotNull(
                'expires_at',
            )
            ->where(
                'expires_at',
                '<=',
                now(),
            );
    }
}
