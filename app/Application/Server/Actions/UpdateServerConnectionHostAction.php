<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateServerConnectionHostAction
{
    public function __construct(
        private RecordSupportAccessAction $recordSupportAccess,
    ) {}

    public function handle(
        User $admin,
        Server $server,
        string $newHost,
        string $reason,
        ?string $ipAddress,
        ?string $userAgent,
    ): Server {
        if (! $admin->isAdmin()) {
            throw new LogicException(
                'Only an administrator may update a server connection host.',
            );
        }

        $normalizedHost = trim($newHost);
        $normalizedReason = trim($reason);

        if (
            filter_var(
                $normalizedHost,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4,
            ) === false
        ) {
            throw ValidationException::withMessages([
                'newHost' => [
                    'آدرس واردشده باید یک IPv4 معتبر باشد.',
                ],
            ]);
        }

        if (
            mb_strlen($normalizedReason) < 5
            || mb_strlen($normalizedReason) > 500
        ) {
            throw ValidationException::withMessages([
                'hostUpdateReason' => [
                    'دلیل تغییر IP باید بین ۵ تا ۵۰۰ کاراکتر باشد.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $admin,
            $server,
            $normalizedHost,
            $normalizedReason,
            $ipAddress,
            $userAgent,
        ): Server {
            $lockedServer = Server::query()
                ->whereKey($server->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldHost = (string) $lockedServer->host;

            if ($oldHost === $normalizedHost) {
                throw ValidationException::withMessages([
                    'newHost' => [
                        'آدرس IP جدید با آدرس فعلی سرور یکسان است.',
                    ],
                ]);
            }

            $duplicateExists = Server::query()
                ->where('user_id', $lockedServer->user_id)
                ->whereKeyNot($lockedServer->getKey())
                ->where('host', $normalizedHost)
                ->where('port', $lockedServer->port)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'newHost' => [
                        'سرور دیگری برای این کاربر با همین آدرس IP و پورت ثبت شده است.',
                    ],
                ]);
            }

            $lockedServer->update([
                'host' => $normalizedHost,
            ]);

            $this->recordSupportAccess->handle(
                admin: $admin,
                server: $lockedServer,
                action: SupportAccessAction::ConnectionHostUpdated,
                reason: $normalizedReason,
                successful: true,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                metadata: [
                    'old_host' => $oldHost,
                    'new_host' => $normalizedHost,
                ],
            );

            return $lockedServer->refresh();
        });
    }
}
