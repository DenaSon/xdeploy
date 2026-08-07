<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

require_once __DIR__
    . '/../../Support/SSH/FakeSshPortSocket.php';

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudProvisioningTimeoutException;
use App\Domain\Cloud\Exceptions\CloudServerNotReadyException;
use App\Domain\Cloud\Exceptions\CloudServerProvisioningException;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Infrastructure\SSH\Services\FakeSshPortSocket;
use App\Models\Server;
use App\Models\User;
use App\Support\SSH\SSHTimeout;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ProvisionCloudServerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeSshPortSocket::ready();
    }

    protected function tearDown(): void
    {
        FakeSshPortSocket::reset();

        parent::tearDown();
    }

    public function test_it_creates_persists_polls_verifies_ssh_and_activates_server(): void
    {
        $catalog = $this->catalog();
        $provisioner = $this->provisioner();
        $ssh = $this->ssh();

        $provisioner
            ->shouldReceive('createServer')
            ->once()
            ->with(
                Mockery::type(
                    CreateCloudServerData::class,
                ),
            )
            ->andReturn(
                $this->createdServer(),
            );

        $provisioner
            ->shouldReceive('findServer')
            ->twice()
            ->with(
                'eu-west1-a',
                'provider-server-id',
            )
            ->andReturn(
                $this->cloudServer(
                    CloudServerStatus::Provisioning,
                ),
                $this->cloudServer(
                    CloudServerStatus::Active,
                    [
                        $this->publicAddress(),
                    ],
                ),
            );

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->with(
                Mockery::on(
                    static fn (Server $server): bool =>
                        $server->cloud_server_id
                            === 'provider-server-id'
                        && $server->host
                            === '185.204.168.213'
                        && $server->username
                            === 'ubuntu'
                        && $server->status
                            === ServerStatus::Inactive,
                ),
            )
            ->andReturnTrue();

        $this->expectCommandReadinessProbe(
            $ssh,
        );

        $this->expectSupportedUbuntuInspection(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: "0\n",
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        $result = $this->action(
            catalog: $catalog,
            provisioner: $provisioner,
            maxAttempts: 3,
            ssh: $ssh,
        )->handle(
            user: $this->createUser(),
            data: $this->createData(),
        );

        $this->assertSame(
            2,
            $result->pollAttempts,
        );

        $this->assertSame(
            '185.204.168.213',
            $result->server->host,
        );

        $this->assertSame(
            'ubuntu',
            $result->server->username,
        );

        $this->assertSame(
            ServerStatus::Active,
            $result->server->status,
        );

        $this->assertSame(
            'temporary-generated-password',
            $result->server->credential,
        );

        $this->assertSame(
            CloudServerStatus::Active,
            $result->cloudServer->status,
        );

        $this->assertDatabaseHas(
            'servers',
            [
                'id' => $result->server->id,

                'host' => '185.204.168.213',

                'port' => 22,

                'username' => 'ubuntu',

                'cloud_provider' => 'arvan',

                'cloud_server_id' =>
                    'provider-server-id',

                'cloud_region' => 'eu-west1-a',

                'status' =>
                    ServerStatus::Active->value,
            ],
        );
    }

    public function test_timeout_preserves_the_inactive_server_record(): void
    {
        $catalog = $this->catalog();
        $provisioner = $this->provisioner();
        $ssh = $this->ssh();

        $provisioner
            ->shouldReceive('createServer')
            ->once()
            ->andReturn(
                $this->createdServer(),
            );

        $provisioner
            ->shouldReceive('findServer')
            ->twice()
            ->with(
                'eu-west1-a',
                'provider-server-id',
            )
            ->andReturn(
                $this->cloudServer(
                    CloudServerStatus::Provisioning,
                ),
            );

        $ssh->shouldNotReceive('connect');
        $ssh->shouldNotReceive('executeWithResult');
        $ssh->shouldNotReceive('disconnect');

        try {
            $this->action(
                catalog: $catalog,
                provisioner: $provisioner,
                maxAttempts: 2,
                ssh: $ssh,
            )->handle(
                user: $this->createUser(),
                data: $this->createData(),
            );

            $this->fail(
                'Expected provisioning timeout was not thrown.',
            );
        } catch (
            CloudProvisioningTimeoutException
        ) {
            $server = Server::query()
                ->where(
                    'cloud_server_id',
                    'provider-server-id',
                )
                ->firstOrFail();

            $this->assertNull(
                $server->host,
            );

            $this->assertSame(
                ServerStatus::Inactive,
                $server->status,
            );

            $this->assertSame(
                'temporary-generated-password',
                $server->credential,
            );

            $this->assertTrue(
                $server->isCloudProvisioned(),
            );
        }
    }

    public function test_failed_provider_state_preserves_the_inactive_server_record(): void
    {
        $catalog = $this->catalog();
        $provisioner = $this->provisioner();
        $ssh = $this->ssh();

        $provisioner
            ->shouldReceive('createServer')
            ->once()
            ->andReturn(
                $this->createdServer(),
            );

        $provisioner
            ->shouldReceive('findServer')
            ->once()
            ->with(
                'eu-west1-a',
                'provider-server-id',
            )
            ->andReturn(
                $this->cloudServer(
                    CloudServerStatus::Failed,
                ),
            );

        $ssh->shouldNotReceive('connect');
        $ssh->shouldNotReceive('executeWithResult');
        $ssh->shouldNotReceive('disconnect');

        try {
            $this->action(
                catalog: $catalog,
                provisioner: $provisioner,
                maxAttempts: 2,
                ssh: $ssh,
            )->handle(
                user: $this->createUser(),
                data: $this->createData(),
            );

            $this->fail(
                'Expected failed provisioning state was not thrown.',
            );
        } catch (
            CloudServerProvisioningException
        ) {
            $server = Server::query()
                ->where(
                    'cloud_server_id',
                    'provider-server-id',
                )
                ->firstOrFail();

            $this->assertNull(
                $server->host,
            );

            $this->assertSame(
                ServerStatus::Inactive,
                $server->status,
            );

            $this->assertSame(
                'temporary-generated-password',
                $server->credential,
            );
        }
    }

    public function test_active_server_without_public_ip_is_not_ready(): void
    {
        $catalog = $this->catalog();
        $provisioner = $this->provisioner();
        $ssh = $this->ssh();

        $provisioner
            ->shouldReceive('createServer')
            ->once()
            ->andReturn(
                $this->createdServer(),
            );

        $provisioner
            ->shouldReceive('findServer')
            ->twice()
            ->with(
                'eu-west1-a',
                'provider-server-id',
            )
            ->andReturn(
                $this->cloudServer(
                    CloudServerStatus::Active,
                ),
            );

        $ssh->shouldNotReceive('connect');
        $ssh->shouldNotReceive('executeWithResult');
        $ssh->shouldNotReceive('disconnect');

        try {
            $this->action(
                catalog: $catalog,
                provisioner: $provisioner,
                maxAttempts: 2,
                ssh: $ssh,
            )->handle(
                user: $this->createUser(),
                data: $this->createData(),
            );

            $this->fail(
                'Expected cloud server not-ready exception was not thrown.',
            );
        } catch (
            CloudServerNotReadyException
        ) {
            $server = Server::query()
                ->where(
                    'cloud_server_id',
                    'provider-server-id',
                )
                ->firstOrFail();

            $this->assertNull(
                $server->host,
            );

            $this->assertSame(
                ServerStatus::Inactive,
                $server->status,
            );
        }
    }

    private function action(
        CloudProviderInterface $catalog,
        CloudServerProvisionerInterface $provisioner,
        int $maxAttempts,
        SSHConnectionInterface $ssh,
    ): ProvisionCloudServerAction {
        /*
         * Resolve the real SSH-readiness action graph through Laravel.
         * This keeps this workflow test aligned with the current production
         * constructor without duplicating every collaborator manually.
         */
        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        $verifySshReadiness =
            $this->app->make(
                VerifyCloudServerSshReadinessAction::class,
            );

        return new ProvisionCloudServerAction(
            catalog: $catalog,

            provisioner: $provisioner,

            createServer: new CreateServerAction,

            verifySshReadiness:
                $verifySshReadiness,

            providerName: 'arvan',

            maxAttempts: $maxAttempts,

            pollDelaySeconds: 0,
        );
    }

    private function expectCommandReadinessProbe(
        SSHConnectionInterface&MockInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool =>
                        str_contains(
                            $command,
                            '__xdeploy_ssh_ready__',
                        ),
                ),
                Mockery::type('int'),
            )
            ->andReturn(
                new SSHResult(
                    output:
                        '__xdeploy_ssh_ready__',
                    exitCode: 0,
                ),
            );
    }

    private function expectSupportedUbuntuInspection(
        SSHConnectionInterface&MockInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool =>
                        str_contains(
                            $command,
                            '/etc/os-release',
                        ),
                ),
                Mockery::type('int'),
            )
            ->andReturn(
                new SSHResult(
                    output: implode(
                        "\n",
                        [
                            'NAME="Ubuntu"',
                            'VERSION_ID="24.04"',
                            'ID=ubuntu',
                            'ID_LIKE=debian',
                            'PRETTY_NAME="Ubuntu 24.04.4 LTS"',
                        ],
                    ),
                    exitCode: 0,
                ),
            );
    }

    private function catalog(): CloudProviderInterface
    {
        /** @var CloudProviderInterface&MockInterface $catalog */
        $catalog = Mockery::mock(
            CloudProviderInterface::class,
        );

        $catalog
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                new CloudRegionData(
                    id: 'eu-west1-a',
                    displayName: 'Europe',
                    country: 'Germany',
                    city: 'Karlsruhe',
                    dataCenter: 'Goethe',
                    canCreateServers: true,
                    isVisible: true,
                    supportsVolumeBacked: true,
                ),
            ]);

        $catalog
            ->shouldReceive('listSizes')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudSizeData(
                    id: 'eco-1-1-0',
                    name: 'eco-small1',
                    regionId: 'eu-west1-a',
                    vCpu: 1,
                    memoryMiB: 1024,
                    diskGiB: 25,
                    category: 'economic',
                    hourlyPrice: null,
                    monthlyPrice: null,
                ),
            ]);

        $catalog
            ->shouldReceive('listImages')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudImageData(
                    id: 'ubuntu-image-id',
                    name: 'Ubuntu 24.04',
                    regionId: 'eu-west1-a',
                    distribution: 'Ubuntu',
                    version: '24.04',
                    architecture: null,
                    minDiskGiB: null,
                    minMemoryMiB: null,
                    supportsSshKey: true,
                    supportsPassword: true,
                ),
            ]);

        $catalog
            ->shouldReceive('listNetworks')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudNetworkData(
                    id: 'network-id',
                    name: 'public-network',
                    regionId: 'eu-west1-a',
                    ipVersion: CloudIpVersion::IPv4,
                    cidr: '192.0.2.0/24',
                    gateway: '192.0.2.1',
                    isActive: true,
                    dhcpEnabled: true,
                ),
            ]);

        $catalog
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudSecurityGroupData(
                    id: 'security-group-id',
                    name: 'default',
                    regionId: 'eu-west1-a',
                    description: null,
                    isDefault: true,
                    isReadOnly: true,
                ),
            ]);

        return $catalog;
    }

    private function provisioner(): CloudServerProvisionerInterface
    {
        /** @var CloudServerProvisionerInterface&MockInterface $provisioner */
        $provisioner = Mockery::mock(
            CloudServerProvisionerInterface::class,
        );

        return $provisioner;
    }

    /**
     * @return SSHConnectionInterface&MockInterface
     */
    private function ssh(): SSHConnectionInterface
    {
        /** @var SSHConnectionInterface&MockInterface $ssh */
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        return $ssh;
    }

    private function createData(): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: 'xdeploy-cloud-server',

            regionId: 'eu-west1-a',

            sizeId: 'eco-1-1-0',

            imageId: 'ubuntu-image-id',

            networkId: 'network-id',

            securityGroupIds: [
                'security-group-id',
            ],

            diskGiB: 25,
        );
    }

    private function createdServer(): CreatedCloudServerData
    {
        return new CreatedCloudServerData(
            id: 'provider-server-id',

            name: 'xdeploy-cloud-server',

            regionId: 'eu-west1-a',

            status: CloudServerStatus::Provisioning,

            username: 'ubuntu',

            createdAt: new DateTimeImmutable(
                '2026-08-04T07:00:00Z',
            ),

            generatedPassword:
                'temporary-generated-password',
        );
    }

    /**
     * @param list<CloudServerAddressData> $addresses
     */
    private function cloudServer(
        CloudServerStatus $status,
        array $addresses = [],
    ): CloudServerData {
        return new CloudServerData(
            id: 'provider-server-id',

            name: 'xdeploy-cloud-server',

            regionId: 'eu-west1-a',

            status: $status,

            username: 'ubuntu',

            sizeId: 'eco-1-1-0',

            imageId: 'ubuntu-image-id',

            createdAt: new DateTimeImmutable(
                '2026-08-04T07:00:00Z',
            ),

            addresses: $addresses,
        );
    }

    private function publicAddress(): CloudServerAddressData
    {
        return new CloudServerAddressData(
            address: '185.204.168.213',

            version: CloudIpVersion::IPv4,

            isPublic: true,
        );
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Cloud Workflow User',
            'phone' => '+4915112345678',
        ]);
    }
}
