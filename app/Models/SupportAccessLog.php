<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Server\Enums\SupportAccessAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'user_id',
        'server_id',
        'action',
        'reason',
        'metadata',
        'successful',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => SupportAccessAction::class,
            'metadata' => 'array',
            'successful' => 'boolean',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'admin_user_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
