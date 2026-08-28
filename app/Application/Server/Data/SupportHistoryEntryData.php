<?php

declare(strict_types=1);

namespace App\Application\Server\Data;

use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\SupportAccessLog;
use Carbon\CarbonImmutable;

final readonly class SupportHistoryEntryData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int $id,
        public SupportAccessAction $action,
        public string $title,
        public string $reason,
        public bool $successful,
        public int $adminUserId,
        public string $adminLabel,
        public array $metadata,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(SupportAccessLog $log): self
    {
        $metadata = is_array($log->metadata)
            ? $log->metadata
            : [];
        $reason = $log->reason;

        if (
            $log->action === SupportAccessAction::ConnectionHostUpdated
            && (! isset($metadata['old_host']) || ! isset($metadata['new_host']))
        ) {
            $matches = [];

            if (
                preg_match(
                    '/^IP:\s*(?<old>[0-9.]+)\s*→\s*(?<new>[0-9.]+)\s*\|\s*(?<reason>.*)$/u',
                    $reason,
                    $matches,
                ) === 1
            ) {
                $metadata['old_host'] = $matches['old'];
                $metadata['new_host'] = $matches['new'];
                $reason = $matches['reason'];
            }
        }

        $admin = $log->adminUser;
        $adminLabel = $admin?->name
            ?: $admin?->phone
            ?: '#'.$log->admin_user_id;

        return new self(
            id: (int) $log->getKey(),
            action: $log->action,
            title: match ($log->action) {
                SupportAccessAction::SshConnectionTest => 'تست اتصال SSH',
                SupportAccessAction::PasskeyConfirmed => 'تأیید Passkey',
                SupportAccessAction::CredentialRevealed => 'نمایش Credential',
                SupportAccessAction::ConnectionHostUpdated => 'تغییر IP سرور',
            },
            reason: $reason,
            successful: (bool) $log->successful,
            adminUserId: (int) $log->admin_user_id,
            adminLabel: $adminLabel,
            metadata: $metadata,
            createdAt: CarbonImmutable::instance($log->created_at),
        );
    }
}
