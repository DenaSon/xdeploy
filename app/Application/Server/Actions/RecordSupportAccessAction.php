<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use Illuminate\Support\Str;
use LogicException;

final readonly class RecordSupportAccessAction
{
    public function handle(
        User $admin,
        Server $server,
        SupportAccessAction $action,
        string $reason,
        bool $successful,
        ?string $ipAddress,
        ?string $userAgent,
    ): SupportAccessLog {
        if (! $admin->isAdmin()) {
            throw new LogicException(
                'Support access can only be recorded for an administrator.',
            );
        }

        $normalizedReason = trim($reason);

        if ($normalizedReason === '') {
            throw new LogicException(
                'Support access reason is required.',
            );
        }

        return SupportAccessLog::query()->create([
            'admin_user_id' => $admin->getKey(),
            'user_id' => $server->user_id,
            'server_id' => $server->getKey(),
            'action' => $action,
            'reason' => Str::limit($normalizedReason, 500, ''),
            'successful' => $successful,
            'ip_address' => is_string($ipAddress) && $ipAddress !== ''
                ? Str::limit($ipAddress, 45, '')
                : null,
            'user_agent' => is_string($userAgent) && $userAgent !== ''
                ? Str::limit($userAgent, 500, '')
                : null,
        ]);
    }
}
