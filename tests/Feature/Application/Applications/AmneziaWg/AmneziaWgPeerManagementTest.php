<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\AmneziaWg;

use App\Application\Applications\AmneziaWg\Actions\CreateAmneziaWgPeerAction;
use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerGateway;
use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerLifecycleService;
use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerProvisioningResult;
use App\Models\AmneziaWgPeer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AmneziaWgPeerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_reserves_sequential_addresses_and_encrypts_client_config(): void
    {
        $server = $this->createServer();
        $gateway = new FakeAmneziaWgPeerGateway;
        $action = new CreateAmneziaWgPeerAction($gateway);

        $first = $action->execute($server, 'iPhone');
        $second = $action->execute($server, 'MacBook');

        self::assertSame('10.8.1.2', $first->ip_address);
        self::assertSame('10.8.1.3', $second->ip_address);
        self::assertSame('client-config-1', $first->client_config);

        $rawConfig = DB::table('amnezia_wg_peers')
            ->where('id', $first->getKey())
            ->value('client_config');

        self::assertIsString($rawConfig);
        self::assertNotSame('client-config-1', $rawConfig);
        self::assertStringNotContainsString('client-config-1', $rawConfig);
    }

    public function test_deactivation_updates_remote_runtime_before_forgetting_client_secret(): void
    {
        $server = $this->createServer();
        $gateway = new FakeAmneziaWgPeerGateway;
        $peer = (new CreateAmneziaWgPeerAction($gateway))
            ->execute($server, 'Tablet');

        (new AmneziaWgPeerLifecycleService($gateway))
            ->deactivate($server, (int) $peer->getKey());

        $peer->refresh();

        self::assertNotNull($peer->revoked_at);
        self::assertNull($peer->client_config);
        self::assertSame([$peer->public_key], $gateway->removedPublicKeys);
    }

    private function createServer(): Server
    {
        $user = User::query()->create([
            'name' => 'AmneziaWG Peer Test',
            'phone' => '09120000031',
        ]);

        return $user->servers()->create([
            'name' => 'Peer Test Server',
            'host' => '192.0.2.31',
            'port' => 22,
            'username' => 'root',
        ]);
    }
}

final class FakeAmneziaWgPeerGateway implements AmneziaWgPeerGateway
{
    public int $sequence = 0;

    /** @var list<string> */
    public array $removedPublicKeys = [];

    public function createPeer(
        string $ipAddress,
        string $endpointHost,
    ): AmneziaWgPeerProvisioningResult {
        $this->sequence++;

        return new AmneziaWgPeerProvisioningResult(
            publicKey: 'public-key-'.$this->sequence,
            clientConfig: 'client-config-'.$this->sequence,
        );
    }

    public function removePeer(string $publicKey): void
    {
        $this->removedPublicKeys[] = $publicKey;
    }

    public function runtimeStates(): array
    {
        return [];
    }
}
