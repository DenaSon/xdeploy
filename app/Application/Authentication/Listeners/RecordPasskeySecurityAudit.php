<?php

declare(strict_types=1);

namespace App\Application\Authentication\Listeners;

use App\Application\Authentication\Actions\RecordSecurityAuditAction;
use App\Domain\Authentication\Enums\SecurityAuditAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

final readonly class RecordPasskeySecurityAudit
{
    public function __construct(
        private RecordSecurityAuditAction $recordAudit,
    ) {}

    public function registered(PasskeyRegistered $event): void
    {
        $this->record(
            user: $event->user,
            action: SecurityAuditAction::PasskeyRegistered,
            context: 'account_management',
            passkeyId: (int) $event->passkey->getKey(),
            passkeyName: $event->passkey->name,
        );
    }

    public function verified(PasskeyVerified $event): void
    {
        $this->record(
            user: $event->user,
            action: SecurityAuditAction::PasskeyVerified,
            context: $this->verificationContext(),
            passkeyId: (int) $event->passkey->getKey(),
            passkeyName: $event->passkey->name,
        );
    }

    public function deleted(PasskeyDeleted $event): void
    {
        $this->record(
            user: $event->user,
            action: SecurityAuditAction::PasskeyDeleted,
            context: $this->hasHttpRequest()
                ? 'account_management'
                : 'admin_recovery',
            passkeyId: (int) $event->passkey->getKey(),
            passkeyName: $event->passkey->name,
        );
    }

    private function record(
        Authenticatable $user,
        SecurityAuditAction $action,
        string $context,
        int $passkeyId,
        ?string $passkeyName,
    ): void {
        $resolvedUser = $user instanceof User
            ? $user
            : null;

        $this->recordAudit->handle(
            user: $resolvedUser,
            action: $action,
            context: $context,
            passkeyId: $passkeyId,
            passkeyName: $passkeyName,
            ipAddress: $this->hasHttpRequest()
                ? request()->ip()
                : null,
            userAgent: $this->hasHttpRequest()
                ? request()->userAgent()
                : null,
        );
    }

    private function verificationContext(): string
    {
        if (! $this->hasHttpRequest()) {
            return 'console';
        }

        return match (request()->route()?->getName()) {
            'passkey.login' => 'login',
            'admin.passkey.verify' => 'admin_confirmation',
            'admin.servers.support.passkey.verify' => 'support_access',
            default => 'verification',
        };
    }

    private function hasHttpRequest(): bool
    {
        return app()->bound('request')
            && request()->route() !== null;
    }
}
