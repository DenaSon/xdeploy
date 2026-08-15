<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Authentication\Enums\SecurityAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SecurityAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'context',
        'passkey_id',
        'passkey_name',
        'successful',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => SecurityAuditAction::class,
            'successful' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
