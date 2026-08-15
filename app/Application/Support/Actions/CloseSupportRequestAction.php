<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Domain\Support\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CloseSupportRequestAction
{
    public function execute(
        User $actor,
        int $supportRequestId,
    ): SupportRequest {
        return DB::transaction(
            function () use (
                $actor,
                $supportRequestId,
            ): SupportRequest {
                $query = SupportRequest::query()
                    ->whereKey($supportRequestId);

                if (! $actor->isAdmin()) {
                    $query->where(
                        'user_id',
                        $actor->getKey(),
                    );
                }

                /** @var SupportRequest $supportRequest */
                $supportRequest = $query
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($supportRequest->isClosed()) {
                    return $supportRequest;
                }

                $supportRequest->forceFill([
                    'status' => SupportRequestStatus::Closed,
                    'closed_at' => now(),
                ])->save();

                return $supportRequest;
            },
        );
    }
}
