<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Models\Order;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

final readonly class FulfillCloudRenewalAction
{
    public function execute(int $orderId): Server
    {
        /** @var Order $snapshot */
        $snapshot = Order::query()
            ->whereKey($orderId)
            ->firstOrFail();

        if (
            $snapshot->type !== OrderType::CloudRenewal
            || $snapshot->server_id === null
        ) {
            throw CloudServerRenewalException::orderNotFulfillable(
                orderId: $snapshot->getKey(),
                status: $snapshot->status->value,
            );
        }

        $serverId = $snapshot->server_id;

        return DB::transaction(
            function () use (
                $orderId,
                $serverId,
            ): Server {
                /*
                 * Server is locked before Order to stay aligned with the
                 * expiration/termination lifecycle boundary.
                 */
                /** @var Server|null $server */
                $server = Server::query()
                    ->whereKey($serverId)
                    ->lockForUpdate()
                    ->first();

                if (! $server instanceof Server) {
                    throw CloudServerRenewalException::notRenewable(
                        $serverId,
                    );
                }

                /** @var Order $order */
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $order->type !== OrderType::CloudRenewal
                    || $order->server_id !== $server->getKey()
                ) {
                    throw CloudServerRenewalException::orderServerMismatch(
                        orderId: $order->getKey(),
                        serverId: (int) $server->getKey(),
                    );
                }

                /*
                 * The Order status is the idempotency boundary. A repeated
                 * payment callback must never add the duration twice.
                 */
                if ($order->status === OrderStatus::Fulfilled) {
                    return $server;
                }

                if ($order->status !== OrderStatus::Paid) {
                    throw CloudServerRenewalException::orderNotFulfillable(
                        orderId: $order->getKey(),
                        status: $order->status->value,
                    );
                }

                if (
                    $server->user_id !== $order->user_id
                    || ! $server->isCloudProvisioned()
                    || $server->expires_at === null
                    || $server->termination_started_at !== null
                    || $server->isTerminated()
                ) {
                    throw CloudServerRenewalException::notRenewable(
                        (int) $server->getKey(),
                    );
                }

                if ($order->duration_hours < 1) {
                    throw CloudServerRenewalException::invalidDuration(
                        $order->getKey(),
                    );
                }

                /*
                 * Renewal extends the existing commercial lifetime. Even if
                 * payment finishes during the short payment-protection window
                 * after expiry, the new period starts at the previous expiry
                 * rather than at callback time.
                 */
                $server->forceFill([
                    'expires_at' => $server->expires_at
                        ->addHours($order->duration_hours),
                ])->saveOrFail();

                $order->forceFill([
                    'status' => OrderStatus::Fulfilled,
                ])->saveOrFail();

                return $server->fresh();
            },
            3,
        );
    }
}
