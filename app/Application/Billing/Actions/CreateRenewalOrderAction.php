<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateRenewalOrderAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
    ) {}

    public function execute(
        User $user,
        int $serverId,
        string $period,
    ): Order {
        $period = trim(
            $period,
        );

        [$server, $sourceOrder] = $this->renewalContext(
            user: $user,
            serverId: $serverId,
        );

        /*
         * Provider catalog calls must remain outside database transactions.
         * Renewal pricing is authoritative at quote creation time and uses
         * the current catalog for the already-provisioned plan/disk shape.
         */
        $price = $this->calculatePrice->execute(
            region: $sourceOrder->region_id,
            sizeId: $sourceOrder->size_id,
            selectedDiskGiB: $sourceOrder->selected_disk_gib,
            period: $period,
        );

        $quoteTtlMinutes = max(
            1,
            (int) config(
                'money.quote_ttl_minutes',
                15,
            ),
        );

        return DB::transaction(
            function () use (
                $user,
                $server,
                $sourceOrder,
                $price,
                $quoteTtlMinutes,
            ): Order {
                /** @var Server $lockedServer */
                $lockedServer = Server::query()
                    ->whereKey(
                        $server->getKey(),
                    )
                    ->where(
                        'user_id',
                        $user->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertCanStartRenewal(
                    $lockedServer,
                );

                /** @var Order|null $lockedSourceOrder */
                $lockedSourceOrder = Order::query()
                    ->whereKey(
                        $sourceOrder->getKey(),
                    )
                    ->where(
                        'user_id',
                        $user->getKey(),
                    )
                    ->where(
                        'server_id',
                        $lockedServer->getKey(),
                    )
                    ->where(
                        'type',
                        OrderType::Provisioning,
                    )
                    ->where(
                        'status',
                        OrderStatus::Fulfilled,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSourceOrder instanceof Order) {
                    throw CloudServerRenewalException::sourceOrderMissing(
                        (int) $lockedServer->getKey(),
                    );
                }

                $this->assertPricingSnapshotStillMatches(
                    server: $lockedServer,
                    sourceOrder: $lockedSourceOrder,
                    price: $price,
                );

                return Order::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => OrderType::Renewal,
                    'server_id' => $lockedServer->getKey(),

                    'region_id' => $price->regionId,
                    'size_id' => $price->sizeId,

                    /*
                     * Renewal does not reprovision the OS. Preserve the
                     * original provisioning snapshot for billing/history.
                     */
                    'image_id' => $lockedSourceOrder->image_id,
                    'image_name' => $lockedSourceOrder->image_name,
                    'image_distribution' => $lockedSourceOrder->image_distribution,
                    'image_version' => $lockedSourceOrder->image_version,

                    'default_disk_gib' => $price->defaultDiskGiB,
                    'selected_disk_gib' => $price->selectedDiskGiB,

                    'period' => $price->period,
                    'duration_hours' => $price->durationHours,

                    'provider_cost' => (int) $price->providerCost,
                    'markup_percent' => $price->markupPercent,
                    'final_amount' => (int) $price->finalAmount,
                    'currency' => $price->currency,

                    'status' => OrderStatus::PendingPayment,

                    'quote_expires_at' => now()->addMinutes(
                        $quoteTtlMinutes,
                    ),

                    'paid_at' => null,
                ]);
            },
        );
    }

    /**
     * @return array{0: Server, 1: Order}
     */
    private function renewalContext(
        User $user,
        int $serverId,
    ): array {
        /** @var Server $server */
        $server = Server::query()
            ->whereKey(
                $serverId,
            )
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->firstOrFail();

        $this->assertCanStartRenewal(
            $server,
        );

        /** @var Order|null $sourceOrder */
        $sourceOrder = Order::query()
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->where(
                'server_id',
                $server->getKey(),
            )
            ->where(
                'type',
                OrderType::Provisioning,
            )
            ->where(
                'status',
                OrderStatus::Fulfilled,
            )
            ->oldest('id')
            ->first();

        if (! $sourceOrder instanceof Order) {
            throw CloudServerRenewalException::sourceOrderMissing(
                (int) $server->getKey(),
            );
        }

        $this->assertSourceMatchesServer(
            server: $server,
            sourceOrder: $sourceOrder,
        );

        return [
            $server,
            $sourceOrder,
        ];
    }

    private function assertCanStartRenewal(
        Server $server,
    ): void {
        if (
            ! $server->isCloudProvisioned()
            || $server->expires_at === null
            || $server->isTerminated()
        ) {
            throw CloudServerRenewalException::serverNotRenewable(
                (int) $server->getKey(),
            );
        }

        if ($server->termination_started_at !== null) {
            throw CloudServerRenewalException::terminationStarted(
                (int) $server->getKey(),
            );
        }

        /*
         * MVP rule: a new payment may only start while the service is still
         * alive. A payment verified just after expiry is handled separately
         * by FulfillPaidRenewalOrderAction as long as termination has not won
         * the row-lock race yet.
         */
        if ($server->hasExpired()) {
            throw CloudServerRenewalException::serverExpired(
                (int) $server->getKey(),
            );
        }
    }

    private function assertSourceMatchesServer(
        Server $server,
        Order $sourceOrder,
    ): void {
        $serverRegion = trim(
            (string) $server->cloud_region,
        );

        if (
            $serverRegion === ''
            || $serverRegion !== trim(
                (string) $sourceOrder->region_id,
            )
        ) {
            throw new LogicException(
                sprintf(
                    'Cloud Server [%d] region does not match its provisioning Order.',
                    $server->getKey(),
                ),
            );
        }
    }

    private function assertPricingSnapshotStillMatches(
        Server $server,
        Order $sourceOrder,
        PurchasePriceData $price,
    ): void {
        $this->assertSourceMatchesServer(
            server: $server,
            sourceOrder: $sourceOrder,
        );

        if (
            $price->regionId !== $sourceOrder->region_id
            || $price->sizeId !== $sourceOrder->size_id
            || $price->selectedDiskGiB !== $sourceOrder->selected_disk_gib
        ) {
            throw new LogicException(
                sprintf(
                    'Cloud Server [%d] provisioning snapshot changed during renewal pricing.',
                    $server->getKey(),
                ),
            );
        }
    }
}
