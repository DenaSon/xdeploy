<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Authentication\Enums\SecurityAuditAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Tests\TestCase;

final class AdminPasskeyRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_glass_command_removes_admin_passkeys_and_audits_recovery(): void
    {
        $admin = User::factory()->create([
            'phone' => '09120000111',
            'is_admin' => true,
        ]);

        $this->createPasskey(
            $admin,
            'recovery-passkey-one',
        );
        $this->createPasskey(
            $admin,
            'recovery-passkey-two',
        );

        $this->artisan(
            'auth:passkeys:reset-admin',
            [
                'phone' => $admin->phone,
                '--force' => true,
            ],
        )->assertExitCode(CommandStatus::SUCCESS);

        $this->assertSame(
            0,
            $admin->passkeys()->count(),
        );

        $this->assertDatabaseHas(
            'security_audit_logs',
            [
                'user_id' => $admin->id,
                'action' => SecurityAuditAction::AdminPasskeysReset->value,
                'context' => 'break_glass_cli',
                'successful' => true,
            ],
        );

        $this->assertDatabaseCount(
            'security_audit_logs',
            3,
        );
    }

    public function test_break_glass_command_refuses_non_admin_account(): void
    {
        $user = User::factory()->create([
            'phone' => '09120000112',
        ]);

        $passkey = $this->createPasskey(
            $user,
            'regular-user-recovery-passkey',
        );

        $this->artisan(
            'auth:passkeys:reset-admin',
            [
                'phone' => $user->phone,
                '--force' => true,
            ],
        )->assertExitCode(CommandStatus::FAILURE);

        $this->assertDatabaseHas('passkeys', [
            'id' => $passkey->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing(
            'security_audit_logs',
            [
                'action' => SecurityAuditAction::AdminPasskeysReset->value,
            ],
        );
    }

    private function createPasskey(
        User $user,
        string $credentialId,
    ) {
        return $user->passkeys()->create([
            'name' => 'Recovery test device',
            'credential_id' => $credentialId,
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);
    }
}
