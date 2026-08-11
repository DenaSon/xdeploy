<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationDelivery extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENDING = 'sending';

    public const string STATUS_DELIVERED = 'delivered';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_FAILED_PERMANENT = 'failed_permanent';

    protected $fillable = [
        'user_id',
        'dedupe_key',
        'notification_type',
        'channel',
        'status',
        'attempts',
        'last_error',
        'delivered_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'attempts' => 'integer',
        'delivered_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    public function isDelivered(): bool
    {
        return $this->status
            === self::STATUS_DELIVERED;
    }
}
