<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Livewire\Servers\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertDontSee($privateKey);
    }
}
