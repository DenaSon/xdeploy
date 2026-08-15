<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Application\Support\SupportContent;
use App\Domain\Support\Enums\SupportMessageAuthorRole;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use App\Models\Server;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateSupportRequestAction
{
    public function execute(
        User $user,
        string $subject,
        SupportRequestCategory $category,
        string $message,
        ?int $serverId = null,
    ): SupportRequest {
        $subject = SupportContent::subject($subject);
        $message = SupportContent::message($message);

        return DB::transaction(
            function () use (
                $user,
                $subject,
                $category,
                $message,
                $serverId,
            ): SupportRequest {
                $ownedServerId = $this->resolveOwnedServerId(
                    user: $user,
                    serverId: $serverId,
                );

                $messageAt = now();

                $supportRequest = SupportRequest::query()->create([
                    'user_id' => $user->getKey(),
                    'server_id' => $ownedServerId,
                    'subject' => $subject,
                    'category' => $category,
                    'status' => SupportRequestStatus::Open,
                    'last_message_at' => $messageAt,
                    'closed_at' => null,
                ]);

                $supportRequest->messages()->create([
                    'author_id' => $user->getKey(),
                    'author_role' => SupportMessageAuthorRole::User,
                    'body' => $message,
                ]);

                return $supportRequest;
            },
        );
    }

    private function resolveOwnedServerId(
        User $user,
        ?int $serverId,
    ): ?int {
        if ($serverId === null) {
            return null;
        }

        /** @var Server $server */
        $server = Server::query()
            ->whereKey($serverId)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        return (int) $server->getKey();
    }
}
