<?php

declare(strict_types=1);

namespace Tests\Feature\PublicEndpoint;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\PublicEndpoint;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicEndpointPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_has_public_endpoint_relationship(): void
    {
        $server = $this->server();

        $endpoint = $server
            ->publicEndpoints()
            ->create([
                'application_type' => ApplicationType::Marzban,
                'domain' => 'panel.example.com',
            ]);

        self::assertSame(
            $server->getKey(),
            $endpoint->server->getKey(),
        );

        self::assertSame(
            ApplicationType::Marzban,
            $endpoint->application_type,
        );

        self::assertFalse(
            $endpoint->isActive(),
        );
    }

    public function test_application_can_have_only_one_endpoint_per_server(): void
    {
        $server = $this->server();

        PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'panel.example.com',
        ]);

        $this->expectException(
            QueryException::class,
        );

        PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'another.example.com',
        ]);
    }

    public function test_same_domain_can_be_reused_on_a_different_server(): void
    {
        $firstServer = $this->server();
        $secondServer = $this->server();

        PublicEndpoint::query()->create([
            'server_id' => $firstServer->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'panel.example.com',
        ]);

        $secondEndpoint = PublicEndpoint::query()->create([
            'server_id' => $secondServer->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'panel.example.com',
        ]);

        self::assertSame(
            $secondServer->getKey(),
            $secondEndpoint->server_id,
        );
    }

    private function server(): Server
    {
        $user = User::query()->create([
            'phone' => '0917000'.str_pad(
                (string) User::query()->count(),
                4,
                '0',
                STR_PAD_LEFT,
            ),
        ]);

        return $user
            ->servers()
            ->create([
                'name' => 'endpoint-test-'.$user->getKey(),
                'host' => '192.0.2.'.(40 + (int) $user->getKey()),
                'port' => 22,
                'username' => 'root',
                'status' => ServerStatus::Active,
            ]);
    }
}
