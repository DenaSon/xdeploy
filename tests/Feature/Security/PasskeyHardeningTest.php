<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Authentication\Enums\SecurityAuditAction;
use App\Livewire\Security\Index;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;
use Livewire\Livewire;
use Tests\TestCase;

final class PasskeyHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_can_delete_their_final_passkey(): void
    {
        $user = User::factory()->create();
        $passkey = $this->passkey(
            user: $user,
            credentialId: 'regular-user-passkey',
        );

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('deletePasskey', $passkey->id)
            ->assertSet(
                'statusMessage',
                'Passkey با موفقیت حذف شد.',
            )
            ->assertSet('securityError', null);

        $this->assertDatabaseMissing('passkeys', [
            'id' => $passkey->id,
        ]);
    }

    public function test_admin_cannot_delete_their_final_passkey(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $passkey = $this->passkey(
            user: $admin,
            credentialId: 'final-admin-passkey',
        );

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('deletePasskey', $passkey->id)
            ->assertSet(
                'securityError',
                'آخرین Passkey حساب مدیر قابل حذف نیست. ابتدا یک Passkey دیگر اضافه کنید.',
            );

        $this->assertDatabaseHas('passkeys', [
            'id' => $passkey->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_delete_one_passkey_when_another_remains(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $first = $this->passkey(
            user: $admin,
            credentialId: 'admin-passkey-one',
        );
        $second = $this->passkey(
            user: $admin,
            credentialId: 'admin-passkey-two',
        );

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('deletePasskey', $first->id)
            ->assertSet(
                'statusMessage',
                'Passkey با موفقیت حذف شد.',
            );

        $this->assertDatabaseMissing('passkeys', [
            'id' => $first->id,
        ]);
        $this->assertDatabaseHas('passkeys', [
            'id' => $second->id,
            'user_id' => $admin->id,
        ]);
        $this->assertSame(
            1,
            $admin->passkeys()->count(),
        );
    }

    public function test_laravel_passkey_events_are_recorded_without_secret_material(): void
    {
        $user = User::factory()->create();
        $passkey = $this->passkey(
            user: $user,
            credentialId: 'audited-passkey',
            name: 'Audited device',
        );

        PasskeyRegistered::dispatch($user, $passkey);
        PasskeyVerified::dispatch($user, $passkey);

        $logs = SecurityAuditLog::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame(
            SecurityAuditAction::PasskeyRegistered,
            $logs[0]->action,
        );
        $this->assertSame(
            SecurityAuditAction::PasskeyVerified,
            $logs[1]->action,
        );
        $this->assertSame($passkey->id, $logs[0]->passkey_id);
        $this->assertSame('Audited device', $logs[0]->passkey_name);
        $this->assertArrayNotHasKey(
            'credential',
            $logs[0]->getAttributes(),
        );
        $this->assertArrayNotHasKey(
            'credential_id',
            $logs[0]->getAttributes(),
        );
    }

    private function passkey(
        User $user,
        string $credentialId,
        string $name = 'Test device',
    ) {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);
    }
}
