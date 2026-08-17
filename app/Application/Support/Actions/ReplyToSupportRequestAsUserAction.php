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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class ReplyToSupportRequestAsUserAction
{
    public function __construct(
        private StoreSupportMessageAttachmentsAction $storeAttachments,
    ) {}

    /**
     * @param array<int, UploadedFile> $attachments
     */
    public function execute(
        User $user,
        int $supportRequestId,
        string $message,
        array $attachments = [],
    ): SupportMessage {
        $message = SupportContent::message($message);
        $attachments = array_values($attachments);

        return DB::transaction(
            function () use (
                $user,
                $supportRequestId,
                $message,
                $attachments,
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

                if ($attachments !== []) {
                    $this->storeAttachments->execute(
                        message: $supportMessage,
                        files: $attachments,
                    );
                }

                $supportRequest->forceFill([
                    'status' => SupportRequestStatus::Open,
                    'last_message_at' => now(),
                ])->save();

                return $supportMessage;
            },
        );
    }
}
