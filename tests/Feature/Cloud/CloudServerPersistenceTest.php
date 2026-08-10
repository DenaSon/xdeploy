<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CloudServerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_inactive_cloud_server_without_a_host(): void
    {
        $user = $this->createUser();

        $server = app(
            CreateServerAction::class,
        )->handle(
            user: $user,

            attributes: [
                'name' => 'xdeploy-cloud-server',

                'host' => null,

                'port' => 22,

                'username' => 'ubuntu',

                'authentication_type' => AuthenticationType::Password,

                'credential' => 'temporary-generated-password',

                'cloud_provider' => 'arvan',

                'cloud_server_id' => 'ff83466c-c0fe-4dc4-9d1d-bde29efd0b45',

                'cloud_region' => 'eu-west1-a',

                'provisioned_at' => now(),
            ],

            status: ServerStatus::Inactive,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $server->status,
        );

        $this->assertNull(
            $server->host,
        );

        $this->assertSame(
            'ubuntu',
            $server->username,
        );

        $this->assertSame(
            'temporary-generated-password',
            $server->credential,
        );

        $this->assertTrue(
            $server->isCloudProvisioned(),
        );

        $this->assertFalse(
            $server->hasConnectionHost(),
        );

        $this->assertNotNull(
            $server->provisioned_at,
        );
    }

    public function test_cloud_credential_is_not_stored_as_plain_text(): void
    {
        $user = $this->createUser();

        $server = app(
            CreateServerAction::class,
        )->handle(
            user: $user,

            attributes: [
                'name' => 'xdeploy-cloud-server',

                'host' => null,

                'port' => 22,

                'username' => 'ubuntu',

                'authentication_type' => AuthenticationType::Password,

                'credential' => 'temporary-generated-password',

                'cloud_provider' => 'arvan',

                'cloud_server_id' => 'provider-server-id',

                'cloud_region' => 'eu-west1-a',

                'provisioned_at' => now(),
            ],

            status: ServerStatus::Inactive,
        );

        $rawCredential = DB::table(
            'servers',
        )
            ->where('id', $server->id)
            ->value('credential');

        $this->assertIsString(
            $rawCredential,
        );

        $this->assertNotSame(
            'temporary-generated-password',
            $rawCredential,
        );

        $this->assertStringStartsWith(
            'xdeploy:credential:v1:',
            $rawCredential,
        );
    }

    public function test_cloud_provider_server_identifier_is_unique_per_provider(): void
    {
        $user = $this->createUser();

        $attributes = [
            'name' => 'xdeploy-cloud-server',

            'host' => null,

            'port' => 22,

            'username' => 'ubuntu',

            'authentication_type' => AuthenticationType::Password,

            'credential' => 'temporary-generated-password',

            'cloud_provider' => 'arvan',

            'cloud_server_id' => 'duplicate-provider-id',

            'cloud_region' => 'eu-west1-a',

            'provisioned_at' => now(),
        ];

        app(
            CreateServerAction::class,
        )->handle(
            user: $user,
            attributes: $attributes,
            status: ServerStatus::Inactive,
        );

        $this->expectException(
            QueryException::class,
        );

        app(
            CreateServerAction::class,
        )->handle(
            user: $user,
            attributes: $attributes,
            status: ServerStatus::Inactive,
        );
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',

            'phone' => '+4915112345678',
        ]);
    }
}
