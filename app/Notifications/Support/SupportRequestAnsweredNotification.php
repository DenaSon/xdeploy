<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use Illuminate\Notifications\Notification;

final class SupportRequestAnsweredNotification extends Notification
{
    public function __construct(
        public readonly int $supportRequestId,
        public readonly string $subject,
    ) {}

    /**
     * @return list<string>
     */
    public function via(
        object $notifiable,
    ): array {
        return [
            'database',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(
        object $notifiable,
    ): array {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        object $notifiable,
    ): array {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'support_request_answered',

            'title' => 'پاسخ جدید پشتیبانی',

            'message' => sprintf(
                'برای درخواست «%s» پاسخ جدیدی ثبت شد.',
                $this->subject,
            ),

            'icon' => 'lucide.message-circle-reply',

            'tone' => 'info',

            'support_request_id' => $this->supportRequestId,

            'action_label' => 'مشاهده درخواست',

            /*
             * The user-facing route is introduced in Phase 2. Keep the URL
             * as a stable relative contract so backend notification behavior
             * can be completed without coupling this phase to Presentation.
             */
            'action_url' => sprintf(
                '/panel/support/%d',
                $this->supportRequestId,
            ),
        ];
    }
}
