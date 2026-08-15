<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'subject',
        'category',
        'status',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'category' => SupportRequestCategory::class,
        'status' => SupportRequestStatus::class,
        'last_message_at' => 'immutable_datetime',
        'closed_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)
            ->oldest('id');
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id
            === (int) $user->getKey();
    }

    public function isClosed(): bool
    {
        return $this->status === SupportRequestStatus::Closed;
    }
}
