<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\AdminPasskeyVerificationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPasskeyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost:8000',
            'passkeys.relying_party_id' => 'localhost',
            'passkeys.allowed_origins' => ['http://localhost:8000'],
            'passkeys.user_handle_secret' => 'testing-passkey-user-handle-secret',
        ]);
    }

    public function test_admin_without_passkey_is_redirected_to_security_management(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('panel.security'))
            ->assertSessionHas('admin_passkey_required', true);
    }

    public function test_admin_with_passkey_requires_fresh_passkey_confirmation(): void
    {
        $admin = $this->adminWithPasskey();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.passkey.confirm'));
    }

    public function test_admin_with_fresh_passkey_verification_can_access_admin(): void
    {
        $admin = $this->adminWithPasskey();

        $this->actingAs($admin)
            ->withSession([
                AdminPasskeyVerificationSession::SESSION_KEY => [
                    'admin_user_id' => $admin->id,
                    'verified_at' => now()->timestamp,
                ],
            ])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_stale_admin_passkey_verification_is_rejected(): void
    {
        $admin = $this->adminWithPasskey();

        $this->actingAs($admin)
            ->withSession([
                AdminPasskeyVerificationSession::SESSION_KEY => [
                    'admin_user_id' => $admin->id,
                    'verified_at' => now()->subHours(2)->timestamp,
                ],
            ])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.passkey.confirm'));
    }

    public function test_passkey_confirmation_options_are_scoped_to_authenticated_admin(): void
    {
        $admin = $this->adminWithPasskey();

        $this->actingAs($admin)
            ->getJson(route('admin.passkey.options'))
            ->assertOk()
            ->assertJsonPath(
                'options.userVerification',
                'required',
            )
            ->assertJsonCount(
                1,
                'options.allowCredentials',
            );
    }

    public function test_non_admin_cannot_open_admin_passkey_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.passkey.confirm'))
            ->assertForbidden();
    }

    protected function automaticallyVerifyAdminPasskey(): bool
    {
        return false;
    }

    private function adminWithPasskey(): User
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $admin->passkeys()->create([
            'name' => 'Admin device',
            'credential_id' => 'YWRtaW4tY3JlZGVudGlhbA',
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        return $admin;
    }
}
