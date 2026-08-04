<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Casts\ServerCredentialCast;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'host',
        'port',
        'username',
        'authentication_type',
        'credential',
        'status',
        'cloud_provider',
        'cloud_server_id',
        'cloud_region',
        'provisioned_at',
    ];

    protected $hidden = [
        'credential',
        'credential_context',
    ];

    protected $casts = [
        'port' => 'integer',

        'credential' => ServerCredentialCast::class,

        'status' => ServerStatus::class,

        'authentication_type' => AuthenticationType::class,

        'provisioned_at' => 'immutable_datetime',
    ];

    /**
     * Server owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    public function isActive(): bool
    {
        return $this->status
            === ServerStatus::Active;
    }

    public function isCloudProvisioned(): bool
    {
        return $this->cloud_provider !== null
            && $this->cloud_server_id !== null;
    }

    public function hasConnectionHost(): bool
    {
        return is_string($this->host)
            && trim($this->host) !== '';
    }

    public function scopeActive(
        Builder $query,
    ): void {
        $query->where(
            'status',
            ServerStatus::Active,
        );
    }
}
