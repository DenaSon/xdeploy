<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Application\Support\SupportContent;
use App\Domain\Support\Enums\SupportMessageAuthorRole;
use App\Domain\Support\Enums\SupportRequestStatus;
use App\Domain\Support\Exceptions\SupportRequestClosedException;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ReplyToSupportRequestAsUserAction
{
    public function execute(
        User $user,
        int $supportRequestId,
        string $message,
    ): SupportMessage {
        $message = SupportContent::message($message);

        return DB::transaction(
            function () use (
                $user,
                $supportRequestId,
                $message,
            ): SupportMessage {
                /** @var SupportRequest $supportRequest */
                $supportRequest = SupportRequest::query()
                    ->whereKey($supportRequestId)
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($supportRequest->isClosed()) {
                    throw SupportRequestClosedException::forRequest(
                        $supportRequestId,
                    );
                }

                $supportMessage = $supportRequest->messages()->create([
                    'author_id' => $user->getKey(),
                    'author_role' => SupportMessageAuthorRole::User,
                    'body' => $message,
                ]);

                $supportRequest->forceFill([
                    'status' => SupportRequestStatus::Open,
                    'last_message_at' => now(),
                ])->save();

                return $supportMessage;
            },
        );
    }
}
