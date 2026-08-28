<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Livewire\Servers\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

final class EditAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_preserves_ssh_key_type_without_exposing_stored_private_key(): void
    {
        $user = User::factory()->create();
        $privateKey = implode("\n", [
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            'stored-private-key-must-not-enter-livewire-state',
            '-----END OPENSSH PRIVATE KEY-----',
        ]);

        $server = $user->servers()->create([
            'name' => 'test-server',
            'host' => '203.0.113.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::SSHKey,
            'credential' => $privateKey,
        ]);

        $this->actingAs($user);

        Livewire::test(
            Edit::class,
            ['server' => $server],
        )
            ->assertSet(
                'authenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->assertSet('credential', '')
            ->assertSee('فقط Private Key را وارد کنید')
            ->assertDontSee($privateKey);
    }

    public function test_changing_authentication_type_requires_a_new_credential(): void
    {
        $user = User::factory()->create();
        $server = $user->servers()->create([
            'name' => 'test-server',
            'host' => '203.0.113.21',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'current-password',
        ]);

        $this->actingAs($user);

        Livewire::test(
            Edit::class,
            ['server' => $server],
        )
            ->call(
                'selectAuthenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->call('update')
            ->assertHasErrors(['credential']);

        $server->refresh();

        $this->assertSame(
            AuthenticationType::Password,
            $server->authentication_type,
        );
        $this->assertSame(
            'current-password',
            $server->credential,
        );
    }

    public function test_authentication_type_can_be_changed_from_password_to_ssh_key(): void
    {
        $user = User::factory()->create();
        $server = $user->servers()->create([
            'name' => 'test-server',
            'host' => '203.0.113.22',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'current-password',
        ]);
        $privateKey = RSA::createKey(1024)
            ->toString('PKCS8');

        $this->actingAs($user);

        Livewire::test(
            Edit::class,
            ['server' => $server],
        )
            ->call(
                'selectAuthenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->set('credential', $privateKey)
            ->call('update')
            ->assertHasNoErrors();

        $server->refresh();

        $this->assertSame(
            AuthenticationType::SSHKey,
            $server->authentication_type,
        );
        $this->assertSame(
            $privateKey,
            $server->credential,
        );
    }
}
