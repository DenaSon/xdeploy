<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AmneziaWgPeer extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'ip_address',
        'public_key',
        'client_config',
        'revoked_at',
    ];

    protected $hidden = [
        'client_config',
    ];

    protected $casts = [
        'client_config' => 'encrypted',
        'revoked_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
