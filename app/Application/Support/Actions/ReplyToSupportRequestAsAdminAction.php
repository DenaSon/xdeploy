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
use App\Notifications\Support\SupportRequestAnsweredNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ReplyToSupportRequestAsAdminAction
{
    public function execute(
        User $admin,
        int $supportRequestId,
        string $message,
    ): SupportMessage {
        if (! $admin->isAdmin()) {
            throw new AuthorizationException(
                'Only administrators may reply as support staff.',
            );
        }

        $message = SupportContent::message($message);

        /** @var array{message: SupportMessage, request: SupportRequest} $result */
        $result = DB::transaction(
            function () use (
                $admin,
                $supportRequestId,
                $message,
            ): array {
                /** @var SupportRequest $supportRequest */
                $supportRequest = SupportRequest::query()
                    ->whereKey($supportRequestId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($supportRequest->isClosed()) {
                    throw SupportRequestClosedException::forRequest(
                        $supportRequestId,
                    );
                }

                $supportMessage = $supportRequest->messages()->create([
                    'author_id' => $admin->getKey(),
                    'author_role' => SupportMessageAuthorRole::Admin,
                    'body' => $message,
                ]);

                $supportRequest->forceFill([
                    'status' => SupportRequestStatus::Answered,
                    'last_message_at' => now(),
                ])->save();

                return [
                    'message' => $supportMessage,
                    'request' => $supportRequest,
                ];
            },
        );

        $this->notifyOwner(
            $result['request'],
        );

        return $result['message'];
    }

    private function notifyOwner(
        SupportRequest $supportRequest,
    ): void {
        try {
            $supportRequest->loadMissing('user');

            $supportRequest->user->notify(
                new SupportRequestAnsweredNotification(
                    supportRequestId: (int) $supportRequest->getKey(),
                    subject: $supportRequest->subject,
                ),
            );
        } catch (Throwable $exception) {
            /*
             * A notification failure must not roll back or misreport a
             * support reply that has already been persisted successfully.
             */
            logger()->warning('support.notification.failed', [
                'support_request_id' => $supportRequest->getKey(),
                'exception_type' => $exception::class,
            ]);
        }
    }
}
