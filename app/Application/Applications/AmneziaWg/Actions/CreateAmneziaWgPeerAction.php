<?php

declare(strict_types=1);

namespace App\Application\Applications\AmneziaWg\Actions;

use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerGateway;
use App\Models\AmneziaWgPeer;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final readonly class CreateAmneziaWgPeerAction
{
    private const int FIRST_CLIENT_HOST = 2;

    private const int LAST_CLIENT_HOST = 254;

    private const string SUBNET_PREFIX = '10.8.1.';

    public function __construct(
        private AmneziaWgPeerGateway $gateway,
    ) {}

    public function execute(
        Server $server,
        string $name,
    ): AmneziaWgPeer {
        $name = trim($name);

        if ($name === '' || Str::length($name) > 60) {
            throw new InvalidArgumentException(
                'Peer name must contain between 1 and 60 characters.',
            );
        }

        $peer = DB::transaction(function () use ($server, $name): AmneziaWgPeer {
            Server::query()
                ->whereKey($server->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return AmneziaWgPeer::query()->create([
                'server_id' => $server->getKey(),
                'name' => $name,
                'ip_address' => $this->allocateIpAddress($server),
            ]);
        });

        $provisioned = null;

        try {
            $provisioned = $this->gateway->createPeer(
                ipAddress: $peer->ip_address,
                endpointHost: (string) $server->host,
            );

            $peer->forceFill([
                'public_key' => $provisioned->publicKey,
                'client_config' => $provisioned->clientConfig,
            ])->saveOrFail();
        } catch (Throwable $throwable) {
            if ($provisioned !== null && $provisioned->publicKey !== '') {
                try {
                    $this->gateway->removePeer(
                        $provisioned->publicKey,
                    );
                } catch (Throwable) {
                    // Best-effort compensation. Runtime inspection remains authoritative.
                }
            }

            $peer->delete();

            throw $throwable;
        }

        return $peer->refresh();
    }

    private function allocateIpAddress(
        Server $server,
    ): string {
        $used = AmneziaWgPeer::query()
            ->where('server_id', $server->getKey())
            ->pluck('ip_address')
            ->all();

        $used = array_fill_keys(
            array_map('strval', $used),
            true,
        );

        for ($host = self::FIRST_CLIENT_HOST; $host <= self::LAST_CLIENT_HOST; $host++) {
            $candidate = self::SUBNET_PREFIX.$host;

            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException(
            'The AmneziaWG client address pool is exhausted.',
        );
    }
}
