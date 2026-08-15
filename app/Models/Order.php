<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Cloud\Enums\CloudProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    use HasFactory;

    protected $attributes = [
        'cloud_provider' => CloudProviderType::Arvan->value,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'server_id',
        'cloud_provider',

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
            'type' => OrderType::class,
            'server_id' => 'integer',
            'cloud_provider' => CloudProviderType::class,

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
     * Current, non-deleted Server associated with this Order.
     *
     * Provisioning Orders receive server_id when provider delivery succeeds;
     * Renewal Orders point to the existing Server from quote creation onward.
     *
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(
            Server::class,
        );
    }

    /**
     * Historical relation that remains available after a Cloud Server
     * reaches the end of its lifecycle and is soft-deleted.
     *
     * @return BelongsTo<Server, $this>
     */
    public function historicalServer(): BelongsTo
    {
        return $this
            ->belongsTo(
                Server::class,
                'server_id',
            )
            ->withTrashed();
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

    public function isProvisioning(): bool
    {
        return $this->type
            === OrderType::Provisioning;
    }

    public function isRenewal(): bool
    {
        return $this->type
            === OrderType::Renewal;
    }
}
