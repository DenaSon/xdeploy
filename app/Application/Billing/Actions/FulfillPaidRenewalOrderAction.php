<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Models\Order;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class FulfillPaidRenewalOrderAction
{
    public function execute(
        int $orderId,
    ): Server {
        return DB::transaction(
            function () use (
                $orderId,
            ): Server {
                /** @var Order $order */
                $order = Order::query()
                    ->whereKey(
                        $orderId,
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $order->isRenewal()) {
                    throw CloudServerRenewalException::wrongOrderType(
                        (int) $order->getKey(),
                    );
                }

                /*
                 * Repeated gateway callbacks and repeated application calls
                 * must never add the purchased duration twice.
                 */
                if ($order->status === OrderStatus::Fulfilled) {
                    return $this->fulfilledServer(
                        $order,
                    );
                }

                if ($order->status !== OrderStatus::Paid) {
                    throw CloudServerRenewalException::forOrderStatus(
                        orderId: (int) $order->getKey(),
                        status: $order->status,
                    );
                }

                if ($order->server_id === null) {
                    throw CloudServerRenewalException::serverUnavailableForOrder(
                        (int) $order->getKey(),
                    );
                }

                /** @var Server|null $server */
                $server = Server::query()
                    ->whereKey(
                        $order->server_id,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $server instanceof Server) {
                    throw CloudServerRenewalException::serverUnavailableForOrder(
                        (int) $order->getKey(),
                    );
                }

                if ($server->user_id !== $order->user_id) {
                    throw CloudServerRenewalException::ownershipMismatch(
                        orderId: (int) $order->getKey(),
                        serverId: (int) $server->getKey(),
                    );
                }

                if (
                    ! $server->isCloudProvisioned()
                    || $server->expires_at === null
                    || $server->isTerminated()
                ) {
                    throw CloudServerRenewalException::serverNotRenewable(
                        (int) $server->getKey(),
                    );
                }

                /*
                 * Termination owns the same Server row lock before it calls
                 * the provider delete API. If termination has already marked
                 * its start, renewal must not race an in-flight deletion.
                 */
                if ($server->termination_started_at !== null) {
                    throw CloudServerRenewalException::terminationStarted(
                        (int) $server->getKey(),
                    );
                }

                if ($order->duration_hours < 1) {
                    throw new LogicException(
                        sprintf(
                            'Renewal Order [%d] has an invalid duration_hours value.',
                            $order->getKey(),
                        ),
                    );
                }

                $now = now();

                /*
                 * Normal renewal preserves every remaining paid hour by
                 * extending from the current expiry.
                 *
                 * A gateway callback may complete just after expiry. If the
                 * termination workflow has not started yet, extend from now
                 * instead of rejecting an already-paid renewal.
                 */
                $base = $server->expires_at->greaterThan(
                    $now,
                )
                    ? $server->expires_at
                    : $now;

                $server->forceFill([
                    'expires_at' => $base->addHours(
                        $order->duration_hours,
                    ),
                ])->saveOrFail();

                $order->forceFill([
                    'status' => OrderStatus::Fulfilled,
                ])->saveOrFail();

                return $server->refresh();
            },
        );
    }

    private function fulfilledServer(
        Order $order,
    ): Server {
        if ($order->server_id === null) {
            throw CloudServerRenewalException::serverUnavailableForOrder(
                (int) $order->getKey(),
            );
        }

        /** @var Server|null $server */
        $server = Server::withTrashed()
            ->whereKey(
                $order->server_id,
            )
            ->first();

        if (! $server instanceof Server) {
            throw CloudServerRenewalException::serverUnavailableForOrder(
                (int) $order->getKey(),
            );
        }

        return $server;
    }
}
