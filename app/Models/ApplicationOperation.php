<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationOperation extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'application_type',
        'operation',
        'status',
        'failure_code',
        'failure_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'application_type' => ApplicationType::class,
        'operation' => ApplicationOperationType::class,
        'status' => ApplicationOperationStatus::class,
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn(
            'status',
            [
                ApplicationOperationStatus::Pending->value,
                ApplicationOperationStatus::Running->value,
            ],
        );
    }

    public function markRunning(): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->where(
                'status',
                ApplicationOperationStatus::Pending->value,
            )
            ->update([
                'status' => ApplicationOperationStatus::Running->value,
                'started_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    public function markSucceeded(): void
    {
        $this->forceFill([
            'status' => ApplicationOperationStatus::Succeeded,
            'finished_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();
    }

    public function markFailed(
        string $failureCode,
        ?string $failureMessage = null,
    ): void {
        if ($this->status === ApplicationOperationStatus::Succeeded) {
            return;
        }

        $this->forceFill([
            'status' => ApplicationOperationStatus::Failed,
            'finished_at' => now(),
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
        ])->save();
    }
}
