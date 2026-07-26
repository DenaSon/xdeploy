<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
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
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
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
}
