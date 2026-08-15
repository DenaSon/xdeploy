<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Domain\Authentication\Enums\SecurityAuditAction;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class RecordSecurityAuditAction
{
    public function handle(
        ?User $user,
        SecurityAuditAction $action,
        ?string $context = null,
        ?int $passkeyId = null,
        ?string $passkeyName = null,
        bool $successful = true,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): SecurityAuditLog {
        return SecurityAuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'context' => is_string($context) && trim($context) !== ''
                ? Str::limit(trim($context), 80, '')
                : null,
            'passkey_id' => $passkeyId,
            'passkey_name' => is_string($passkeyName) && trim($passkeyName) !== ''
                ? Str::limit(trim($passkeyName), 80, '')
                : null,
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
