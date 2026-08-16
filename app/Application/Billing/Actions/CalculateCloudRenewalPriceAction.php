<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use LogicException;

final readonly class CalculateCloudRenewalPriceAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
    ) {}

    public function execute(
        User $user,
        int $serverId,
        string $period,
    ): PurchasePriceData {
        /** @var Server $server */
        $server = Server::query()
            ->whereKey($serverId)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertCanQuoteRenewal($server);

        /** @var Order|null $sourceOrder */
        $sourceOrder = Order::query()
            ->where('user_id', $user->getKey())
            ->where('server_id', $server->getKey())
            ->where('type', OrderType::Provisioning)
            ->where('status', OrderStatus::Fulfilled)
            ->oldest('id')
            ->first();

        if (! $sourceOrder instanceof Order) {
            throw CloudServerRenewalException::sourceOrderMissing(
                (int) $server->getKey(),
            );
        }

        $provider = $sourceOrder->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new LogicException(
                sprintf(
                    'Provisioning Order [%d] has no valid cloud provider.',
                    $sourceOrder->getKey(),
                ),
            );
        }

        $serverRegion = trim((string) $server->cloud_region);
        $orderRegion = trim((string) $sourceOrder->region_id);
        $serverProvider = $server->cloud_provider;

        if (
            $serverRegion === ''
            || $serverRegion !== $orderRegion
        ) {
            throw new LogicException(
                sprintf(
                    'Cloud Server [%d] region does not match its provisioning Order.',
                    $server->getKey(),
                ),
            );
        }

        if (
            ! $serverProvider instanceof CloudProviderType
            || $serverProvider !== $provider
        ) {
            throw new LogicException(
                sprintf(
                    'Cloud Server [%d] provider does not match its provisioning Order.',
                    $server->getKey(),
                ),
            );
        }

        return $this->calculatePrice->execute(
            region: $sourceOrder->region_id,
            sizeId: $sourceOrder->size_id,
            selectedDiskGiB: $sourceOrder->selected_disk_gib,
            period: trim($period),
            provider: $provider,
        );
    }

    private function assertCanQuoteRenewal(
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

        if ($server->hasExpired()) {
            throw CloudServerRenewalException::serverExpired(
                (int) $server->getKey(),
            );
        }
    }
}
