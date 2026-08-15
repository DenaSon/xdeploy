<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Authentication\Actions\RecordSecurityAuditAction;
use App\Domain\Authentication\Enums\SecurityAuditAction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\DeletePasskey;
use Symfony\Component\Console\Command\Command as CommandStatus;

final class ResetAdminPasskeys extends Command
{
    protected $signature = 'auth:passkeys:reset-admin
        {phone : Mobile number of the administrator}
        {--force : Skip the interactive confirmation}';

    protected $description = 'Break-glass reset of all Passkeys for an administrator account';

    public function handle(
        DeletePasskey $deletePasskey,
        RecordSecurityAuditAction $recordAudit,
    ): int {
        $phone = trim((string) $this->argument('phone'));

        $admin = User::query()
            ->where('phone', $phone)
            ->first();

        if (! $admin instanceof User) {
            $this->error('User not found.');

            return CommandStatus::FAILURE;
        }

        if (! $admin->isAdmin()) {
            $this->error('The selected user is not an administrator.');

            return CommandStatus::FAILURE;
        }

        $count = $admin->passkeys()->count();

        if ($count === 0) {
            $this->warn('This administrator does not have any Passkeys to reset.');

            return CommandStatus::SUCCESS;
        }

        if (
            ! $this->option('force')
            && ! $this->confirm(
                sprintf(
                    'Delete all %d Passkey(s) for administrator %s?',
                    $count,
                    $phone,
                ),
                false,
            )
        ) {
            $this->info('Recovery cancelled.');

            return CommandStatus::FAILURE;
        }

        DB::transaction(function () use (
            $admin,
            $deletePasskey,
            $recordAudit,
        ): void {
            $passkeys = $admin->passkeys()
                ->lockForUpdate()
                ->get();

            foreach ($passkeys as $passkey) {
                $deletePasskey(
                    $admin,
                    $passkey,
                );
            }

            $recordAudit->handle(
                user: $admin,
                action: SecurityAuditAction::AdminPasskeysReset,
                context: 'break_glass_cli',
            );
        });

        $this->warn('All administrator Passkeys were removed.');
        $this->info('The administrator must authenticate with Phone + OTP and register a new Passkey before accessing /admin again.');

        return CommandStatus::SUCCESS;
    }
}
