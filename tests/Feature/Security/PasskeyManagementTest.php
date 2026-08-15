<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Security\Index;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Livewire\Livewire;
use Tests\TestCase;

final class PasskeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_requires_authentication(): void
    {
        $this->get(route('panel.security'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_security_page(): void
    {
        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $this->actingAs($user)
            ->get(route('panel.security'))
            ->assertOk()
            ->assertSee('امنیت حساب')
            ->assertSee('09123456789')
            ->assertSee('Passkeys');
    }

    public function test_passkey_login_routes_are_not_exposed_in_phase_one(): void
    {
        $this->get('/passkeys/login/options')
            ->assertNotFound();

        $this->postJson('/passkeys/login', [])
            ->assertNotFound();
    }

    public function test_registration_options_are_available_only_to_authenticated_users(): void
    {
        $this->get(route('panel.security.passkeys.options'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        config([
            'passkeys.relying_party_id' => 'localhost',
            'passkeys.allowed_origins' => ['http://localhost:8000'],
            'passkeys.user_handle_secret' => 'testing-passkey-user-handle-secret',
        ]);

        $this->actingAs($user)
            ->getJson(route('panel.security.passkeys.options'))
            ->assertOk()
            ->assertJsonStructure([
                'options' => [
                    'challenge',
                    'rp',
                    'user',
                    'pubKeyCredParams',
                ],
            ]);
    }

    public function test_user_can_delete_own_passkey(): void
    {
        $user = User::factory()->create();

        $passkey = $user->passkeys()->create([
            'name' => 'Windows Hello',
            'credential_id' => 'credential-owned-by-user',
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('deletePasskey', $passkey->id)
            ->assertSet(
                'statusMessage',
                'Passkey با موفقیت حذف شد.',
            );

        $this->assertDatabaseMissing('passkeys', [
            'id' => $passkey->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_passkey(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $passkey = $owner->passkeys()->create([
            'name' => 'Owner device',
            'credential_id' => 'credential-owned-by-another-user',
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        try {
            Livewire::actingAs($otherUser)
                ->test(Index::class)
                ->call('deletePasskey', $passkey->id);

            $this->fail('Expected an ownership-scoped model lookup to fail.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('passkeys', [
                'id' => $passkey->id,
                'user_id' => $owner->id,
            ]);
        }
    }

    public function test_user_implements_passkey_contract_without_exposing_full_phone_as_username(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'phone' => '09123456789',
        ]);

        $this->assertInstanceOf(PasskeyUser::class, $user);
        $this->assertSame('091•••••789', $user->getPasskeyUsername());
        $this->assertStringNotContainsString(
            '09123456789',
            $user->getPasskeyUsername(),
        );
    }
}
