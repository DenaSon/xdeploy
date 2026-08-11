<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateCloudRenewalOrderAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
    ) {}

    public function execute(
        User $user,
        int $serverId,
        string $period,
    ): Order {
        /** @var Server $server */
        $server = Server::query()
            ->whereKey($serverId)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertRenewable(
            $server,
        );

        $purchase = $this->purchaseSnapshot(
            server: $server,
            user: $user,
        );

        /*
         * Renewal pricing is authoritative and current. Only the immutable
         * infrastructure selection is reused from the original purchase;
         * old monetary values are never copied forward.
         */
        $price = $this->calculatePrice->execute(
            region: $purchase->region_id,
            sizeId: $purchase->size_id,
            selectedDiskGiB: $purchase->selected_disk_gib,
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
                $serverId,
                $purchase,
                $price,
                $quoteTtlMinutes,
            ): Order {
                /** @var Server $lockedServer */
                $lockedServer = Server::query()
                    ->whereKey($serverId)
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertRenewable(
                    $lockedServer,
                );

                $quoteExpiresAt = now()->addMinutes(
                    $quoteTtlMinutes,
                );

                if (
                    $lockedServer->expires_at !== null
                    && $lockedServer->expires_at
                        ->lessThan($quoteExpiresAt)
                ) {
                    /*
                     * Never leave a fresh payable renewal quote alive after
                     * the Server itself has reached its commercial expiry.
                     */
                    $quoteExpiresAt =
                        $lockedServer->expires_at;
                }

                return Order::query()->create([
                    'type' => OrderType::CloudRenewal,
                    'user_id' => $user->getKey(),
                    'server_id' => $lockedServer->getKey(),

                    'region_id' => $price->regionId,
                    'size_id' => $price->sizeId,

                    'image_id' => $purchase->image_id,
                    'image_name' => $purchase->image_name,
                    'image_distribution' => $purchase->image_distribution,
                    'image_version' => $purchase->image_version,

                    'default_disk_gib' => $price->defaultDiskGiB,
                    'selected_disk_gib' => $price->selectedDiskGiB,

                    'period' => $price->period,
                    'duration_hours' => $price->durationHours,

                    'provider_cost' => (int) $price->providerCost,
                    'markup_percent' => $price->markupPercent,
                    'final_amount' => (int) $price->finalAmount,
                    'currency' => $price->currency,

                    'status' => OrderStatus::PendingPayment,
                    'quote_expires_at' => $quoteExpiresAt,
                    'paid_at' => null,
                ]);
            },
            3,
        );
    }

    private function assertRenewable(Server $server): void
    {
        if (
            ! $server->isCloudProvisioned()
            || $server->expires_at === null
            || $server->hasExpired()
            || $server->termination_started_at !== null
            || $server->isTerminated()
        ) {
            throw CloudServerRenewalException::notRenewable(
                (int) $server->getKey(),
            );
        }
    }

    private function purchaseSnapshot(
        Server $server,
        User $user,
    ): Order {
        /** @var Order|null $purchase */
        $purchase = Order::query()
            ->where('server_id', $server->getKey())
            ->where('user_id', $user->getKey())
            ->where('type', OrderType::CloudPurchase->value)
            ->where('status', OrderStatus::Fulfilled->value)
            ->oldest('id')
            ->first();

        if (! $purchase instanceof Order) {
            throw CloudServerRenewalException::purchaseSnapshotMissing(
                (int) $server->getKey(),
            );
        }

        return $purchase;
    }
}
