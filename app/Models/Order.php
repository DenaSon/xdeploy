<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'server_id',

        'region_id',
        'size_id',

        'image_id',
        'image_name',
        'image_distribution',
        'image_version',

        'default_disk_gib',
        'selected_disk_gib',

        'period',
        'duration_hours',

        'provider_cost',
        'markup_percent',
        'final_amount',
        'currency',

        'status',

        'quote_expires_at',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'server_id' => 'integer',

            'default_disk_gib' => 'integer',
            'selected_disk_gib' => 'integer',

            'duration_hours' => 'integer',

            'provider_cost' => 'integer',
            'markup_percent' => 'integer',
            'final_amount' => 'integer',

            'status' => OrderStatus::class,

            'quote_expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(
            Server::class,
        );
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class,
        );
    }
}
