<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Application\Shared\Enums\ApplicationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicEndpoint extends Model
{
    protected $fillable = [
        'server_id',
        'application_type',
        'domain',
        'activated_at',
    ];

    protected $casts = [
        'application_type' => ApplicationType::class,
        'activated_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(
            Server::class,
        );
    }

    public function isActive(): bool
    {
        return $this->activated_at !== null;
    }
}
