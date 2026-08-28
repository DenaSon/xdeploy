<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Exceptions\OrderNotProvisionableException;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudServerBootstrapCredentialData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class ProvisionPaidOrderAction
{
    public function __construct(
        private ProvisionCloudServerAction $provisionCloudServer,
        private BuildCloudServerDataFromOrderAction $buildCloudServerData,
        private CloudProviderRegistryInterface $providers,
        private CreateServerAction $createServer,
    ) {}

    public function execute(
        int $orderId,
    ): Server {
        [$order, $shouldCreateServer] = $this->prepareOrder(
            $orderId,
        );

        $provider = $order->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new LogicException(
                sprintf(
                    'Order [%d] has no valid cloud provider.',
                    $order->getKey(),
                ),
            );
        }

        if (! $shouldCreateServer) {
            return $this->recoverExistingProvisioning(
                order: $order,
                provider: $provider,
            );
        }

        try {
            $data = $this->buildCloudServerData->execute(
                $order,
            );

            $user = $order->user;

            if (! $user instanceof User) {
                throw new LogicException(
                    sprintf(
                        'Order [%d] has no valid owner.',
                        $order->getKey(),
                    ),
                );
            }

            $result = $this->provisionCloudServer->provisionProviderResource(
                user: $user,
                data: $data,
                provider: $provider,
            );

            return $this->markFulfilled(
                orderId: $order->getKey(),
                server: $result->server,
            );
        } catch (Throwable $exception) {
            $recoveredServer = $this->findRecoverableServer(
                order: $order,
                provider: $provider,
            );

            if ($recoveredServer instanceof Server) {
                if (
                    $recoveredServer->isActive()
                    || $this->hasProviderDeliveryEvidence(
                        $recoveredServer,
                    )
                ) {
                    return $this->markFulfilled(
                        orderId: $order->getKey(),
                        server: $recoveredServer,
                    );
                }

                $this->attachServerWithoutChangingStatus(
                    orderId: $order->getKey(),
                    server: $recoveredServer,
                );

                throw $exception;
            }

            $this->markFailed(
                orderId: $order->getKey(),
                server: null,
            );

            throw $exception;
        }
    }

    /** @return array{0: Order, 1: bool} */
    private function prepareOrder(
        int $orderId,
    ): array {
        return DB::transaction(
            function () use ($orderId): array {
                /** @var Order $order */
                $order = Order::query()
                    ->with([
                        'user',
                        'server',
                    ])
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status === OrderStatus::Fulfilled) {
                    if (! $order->server instanceof Server) {
                        throw OrderNotProvisionableException::fulfilledWithoutServer(
                            $order->getKey(),
                        );
                    }

                    return [$order, false];
                }

                if (
                    $order->status === OrderStatus::Provisioning
                    || $order->status === OrderStatus::Failed
                ) {
                    return [$order, false];
                }

                if ($order->status !== OrderStatus::Paid) {
                    throw OrderNotProvisionableException::forStatus(
                        orderId: $order->getKey(),
                        status: $order->status,
                    );
                }

                $order->forceFill([
                    'status' => OrderStatus::Provisioning,
                ])->save();

                $order->load([
                    'user',
                    'server',
                ]);

                return [$order, true];
            },
        );
    }

    private function recoverExistingProvisioning(
        Order $order,
        CloudProviderType $provider,
    ): Server {
        if ($order->status === OrderStatus::Fulfilled) {
            $server = $order->server;

            if ($server instanceof Server) {
                return $server;
            }

            throw OrderNotProvisionableException::fulfilledWithoutServer(
                $order->getKey(),
            );
        }

        $server = $this->findRecoverableServer(
            order: $order,
            provider: $provider,
        );

        if ($server instanceof Server) {
            if (
                $server->isActive()
                || $this->hasProviderDeliveryEvidence($server)
            ) {
                return $this->markFulfilled(
                    orderId: $order->getKey(),
                    server: $server,
                );
            }

            $this->attachServerWithoutChangingStatus(
                orderId: $order->getKey(),
                server: $server,
            );
        }

        if ($order->status === OrderStatus::Failed) {
            throw OrderNotProvisionableException::forStatus(
                orderId: $order->getKey(),
                status: $order->status,
            );
        }

        throw OrderNotProvisionableException::alreadyProvisioning(
            $order->getKey(),
        );
    }

    private function findRecoverableServer(
        Order $order,
        CloudProviderType $provider,
    ): ?Server {
        $attachedServer = $order->server;

        if ($attachedServer instanceof Server) {
            return $attachedServer;
        }

        $providerServerName = $this->buildCloudServerData->serverName(
            $order,
        );

        try {
            /** @var CloudServerInventoryInterface $inventory */
            $inventory = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerInventoryInterface::class,
            );

            $matches = array_values(
                array_filter(
                    $inventory->listServers($order->region_id),
                    static fn (CloudServerData $cloudServer): bool => $cloudServer->name
                        === $providerServerName,
                ),
            );
        } catch (Throwable) {
            return null;
        }

        if (count($matches) !== 1) {
            return null;
        }

        $providerServerId = trim($matches[0]->id);

        if ($providerServerId === '') {
            return null;
        }

        $existing = $this->findLocalProviderServer(
            order: $order,
            provider: $provider,
            providerServerId: $providerServerId,
        );

        if ($existing instanceof Server) {
            return $existing;
        }

        return $this->adoptProviderServer(
            order: $order,
            provider: $provider,
            providerServerId: $providerServerId,
        );
    }

    private function findLocalProviderServer(
        Order $order,
        CloudProviderType $provider,
        string $providerServerId,
    ): ?Server {
        return Server::query()
            ->where('user_id', $order->user_id)
            ->where('cloud_provider', $provider->value)
            ->where('cloud_server_id', $providerServerId)
            ->where('cloud_region', $order->region_id)
            ->first();
    }

    private function adoptProviderServer(
        Order $order,
        CloudProviderType $provider,
        string $providerServerId,
    ): ?Server {
        try {
            /** @var CloudServerProvisionerInterface $provisioner */
            $provisioner = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerProvisionerInterface::class,
            );

            $cloudServer = $provisioner->findServer(
                region: $order->region_id,
                serverId: $providerServerId,
            );

            $host = $cloudServer->firstPublicIpv4();
            $username = trim((string) $cloudServer->username);

            if ($host === null || $username === '') {
                return null;
            }

            $credential = $this->recoverCredential(
                order: $order,
                provider: $provider,
                cloudServer: $cloudServer,
            );

            if (! $credential instanceof CloudServerBootstrapCredentialData) {
                return null;
            }

            $user = $order->user;

            if (! $user instanceof User) {
                $user = User::query()->find($order->user_id);
            }

            if (! $user instanceof User) {
                throw new LogicException(
                    sprintf(
                        'Order [%d] has no valid owner for provider resource recovery.',
                        $order->getKey(),
                    ),
                );
            }

            try {
                return $this->createServer->handle(
                    user: $user,
                    attributes: [
                        'name' => $cloudServer->name,
                        'host' => $host,
                        'port' => 22,
                        'username' => $username,
                        'authentication_type' => $credential->authenticationType,
                        'credential' => $credential->credential(),
                        'cloud_provider' => $provider->value,
                        'cloud_server_id' => $providerServerId,
                        'cloud_region' => $order->region_id,
                        'provisioned_at' => $cloudServer->createdAt ?? now(),
                    ],
                    status: ServerStatus::Inactive,
                );
            } catch (Throwable $exception) {
                $existing = $this->findLocalProviderServer(
                    order: $order,
                    provider: $provider,
                    providerServerId: $providerServerId,
                );

                if ($existing instanceof Server) {
                    return $existing;
                }

                throw $exception;
            }
        } catch (Throwable) {
            return null;
        }
    }

    private function recoverCredential(
        Order $order,
        CloudProviderType $provider,
        CloudServerData $cloudServer,
    ): ?CloudServerBootstrapCredentialData {
        $generatedPassword = $cloudServer->generatedPassword();

        if (
            is_string($generatedPassword)
            && trim($generatedPassword) !== ''
        ) {
            return new CloudServerBootstrapCredentialData(
                authenticationType: AuthenticationType::Password,
                credential: $generatedPassword,
            );
        }

        if ($this->providers->supportsCapability(
            provider: $provider,
            capability: CloudServerBootstrapCredentialSourceInterface::class,
        )) {
            /** @var CloudServerBootstrapCredentialSourceInterface $source */
            $source = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerBootstrapCredentialSourceInterface::class,
            );

            return $source->bootstrapCredential(
                request: $this->buildCloudServerData->execute($order),
                server: new CreatedCloudServerData(
                    id: $cloudServer->id,
                    name: $cloudServer->name,
                    regionId: $cloudServer->regionId,
                    status: $cloudServer->status,
                    username: $cloudServer->username,
                    createdAt: $cloudServer->createdAt,
                    generatedPassword: null,
                ),
            );
        }

        if (! $this->providers->supportsCapability(
            provider: $provider,
            capability: CloudServerCredentialManagerInterface::class,
        )) {
            return null;
        }

        /** @var CloudServerCredentialManagerInterface $credentials */
        $credentials = $this->providers->resolveCapability(
            provider: $provider,
            capability: CloudServerCredentialManagerInterface::class,
        );

        $password = $credentials->resetRootPassword(
            region: $order->region_id,
            serverId: $cloudServer->id,
        )->password;

        if (trim($password) === '') {
            return null;
        }

        return new CloudServerBootstrapCredentialData(
            authenticationType: AuthenticationType::Password,
            credential: $password,
        );
    }

    private function hasProviderDeliveryEvidence(
        Server $server,
    ): bool {
        $provider = $server->cloud_provider;
        $providerServerId = trim((string) $server->cloud_server_id);

        return $provider instanceof CloudProviderType
            && $providerServerId !== ''
            && $server->hasConnectionHost()
            && $server->hasCredential();
    }

    private function markFulfilled(
        int $orderId,
        Server $server,
    ): Server {
        DB::transaction(
            function () use ($orderId, $server): void {
                /** @var Order $order */
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var Server $lockedServer */
                $lockedServer = Server::query()
                    ->whereKey($server->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedServer->expires_at === null) {
                    if ($lockedServer->provisioned_at === null) {
                        throw new LogicException(
                            sprintf(
                                'Cloud Server [%d] has no provisioned_at timestamp.',
                                $lockedServer->getKey(),
                            ),
                        );
                    }

                    if ($order->duration_hours < 1) {
                        throw new LogicException(
                            sprintf(
                                'Order [%d] has an invalid duration_hours value.',
                                $order->getKey(),
                            ),
                        );
                    }

                    $lockedServer->forceFill([
                        'expires_at' => $lockedServer
                            ->provisioned_at
                            ->addHours($order->duration_hours),
                    ])->saveOrFail();
                }

                $order->forceFill([
                    'server_id' => $lockedServer->getKey(),
                    'status' => OrderStatus::Fulfilled,
                ])->save();
            },
        );

        return $server->fresh();
    }

    private function markFailed(
        int $orderId,
        ?Server $server,
    ): void {
        DB::transaction(
            function () use ($orderId, $server): void {
                /** @var Order $order */
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status === OrderStatus::Fulfilled) {
                    return;
                }

                $attributes = [
                    'status' => OrderStatus::Failed,
                ];

                if ($server instanceof Server) {
                    $attributes['server_id'] = $server->getKey();
                }

                $order->forceFill($attributes)->save();
            },
        );
    }

    private function attachServerWithoutChangingStatus(
        int $orderId,
        Server $server,
    ): void {
        DB::transaction(
            function () use ($orderId, $server): void {
                /** @var Order $order */
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->server_id !== null) {
                    return;
                }

                $order->forceFill([
                    'server_id' => $server->getKey(),
                ])->save();
            },
        );
    }
}
