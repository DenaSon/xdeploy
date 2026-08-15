<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Exceptions\OrderNotProvisionableException;
use App\Domain\Cloud\Enums\CloudProviderType;
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

        $serverName = $this->buildCloudServerData->serverName(
            $order,
        );

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
                serverName: $serverName,
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

        $server = $order->server;

        if (! $server instanceof Server) {
            $server = $this->findRecoverableServer(
                order: $order,
                provider: $provider,
                serverName: $this->buildCloudServerData->serverName(
                    $order,
                ),
            );
        }

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
        string $serverName,
    ): ?Server {
        return Server::query()
            ->where('user_id', $order->user_id)
            ->where('name', $serverName)
            ->where('cloud_provider', $provider->value)
            ->where('cloud_region', $order->region_id)
            ->latest('id')
            ->first();
    }

    private function hasProviderDeliveryEvidence(
        Server $server,
    ): bool {
        $provider = trim((string) $server->cloud_provider);
        $providerServerId = trim((string) $server->cloud_server_id);

        return $provider !== ''
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
