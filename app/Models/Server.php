<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
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
    ];

    protected $casts = [
        'port' => 'integer',
        'credential' => 'encrypted',
        'status' => ServerStatus::class,
        'authentication_type' => AuthenticationType::class,
    ];

    /**
     * Server owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === ServerStatus::Active;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', ServerStatus::Active);
    }
}
