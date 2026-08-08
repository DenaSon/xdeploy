<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Server;
use Illuminate\Console\Command;
use Throwable;

final class ProvisionPaidOrderCommand extends Command
{
    protected $signature = 'order:provision
        {order : Paid xDeploy Order ID}
        {--execute : Confirm that a real billable cloud server may be created}';

    protected $description =
        'Provision the cloud server purchased by a paid xDeploy Order.';

    public function handle(
        ProvisionPaidOrderAction $action,
    ): int {
        if (! $this->option('execute')) {
            $this->components->error(
                'This command may create a real billable cloud server. Re-run it with --execute.',
            );

            return self::INVALID;
        }

        $order = $this->resolveOrder();

        if (! $order instanceof Order) {
            return self::FAILURE;
        }

        $this->displaySummary(
            $order,
        );

        if (
            $order->status
            !== OrderStatus::Paid
            && $order->status
            !== OrderStatus::Provisioning
            && $order->status
            !== OrderStatus::Fulfilled
        ) {
            $this->components->error(
                sprintf(
                    'Order cannot be provisioned while status is [%s].',
                    $order->status->value,
                ),
            );

            return self::FAILURE;
        }

        $this->newLine();

        $this->components->warn(
            'This operation can create a real provider resource and incur charges.',
        );

        $this->components->warn(
            'Never re-run provider creation manually if this Order is already provisioning.',
        );

        if (
            ! $this->confirm(
                'Continue with this Order?',
                false,
            )
        ) {
            $this->components->info(
                'Order provisioning cancelled.',
            );

            return self::SUCCESS;
        }

        try {
            $server = $action->execute(
                $order->getKey(),
            );

            $freshOrder = $order->fresh();

            $this->newLine();

            $this->components->info(
                'Paid Order provisioning completed successfully.',
            );

            $this->displayResult(
                order: $freshOrder,
                server: $server,
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $freshOrder = $order->fresh([
                'server',
            ]);

            $this->newLine();

            $this->components->error(
                'Paid Order provisioning did not complete successfully.',
            );

            if ($freshOrder instanceof Order) {
                $this->table(
                    [
                        'Field',
                        'Value',
                    ],
                    [
                        [
                            'Order ID',
                            (string) $freshOrder->getKey(),
                        ],
                        [
                            'Order Status',
                            $freshOrder->status->value,
                        ],
                        [
                            'xDeploy Server ID',
                            $freshOrder->server_id !== null
                                ? (string) $freshOrder->server_id
                                : 'Not linked',
                        ],
                    ],
                );
            }

            $this->components->warn(
                'Inspect the Order and any linked/recoverable Server before attempting another action.',
            );

            return self::FAILURE;
        }
    }

    private function resolveOrder(): ?Order
    {
        $argument = $this->argument(
            'order',
        );

        if (
            ! is_string($argument)
            && ! is_int($argument)
        ) {
            $this->components->error(
                'Order ID is invalid.',
            );

            return null;
        }

        $orderId = (string) $argument;

        if (
            ! ctype_digit($orderId)
            || (int) $orderId < 1
        ) {
            $this->components->error(
                'Order ID must be a positive integer.',
            );

            return null;
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->with([
                'user',
                'server',
            ])
            ->find(
                (int) $orderId,
            );

        if (! $order instanceof Order) {
            $this->components->error(
                sprintf(
                    'Order [%s] was not found.',
                    $orderId,
                ),
            );

            return null;
        }

        return $order;
    }

    private function displaySummary(
        Order $order,
    ): void {
        $this->newLine();

        $this->components->info(
            'Paid Order provisioning summary',
        );

        $this->table(
            [
                'Field',
                'Value',
            ],
            [
                [
                    'Order ID',
                    (string) $order->getKey(),
                ],
                [
                    'User ID',
                    (string) $order->user_id,
                ],
                [
                    'Status',
                    $order->status->value,
                ],
                [
                    'Region',
                    $order->region_id,
                ],
                [
                    'Size',
                    $order->size_id,
                ],
                [
                    'Image',
                    sprintf(
                        '%s (%s)',
                        $order->image_name,
                        $order->image_id,
                    ),
                ],
                [
                    'Disk',
                    "{$order->selected_disk_gib} GiB",
                ],
                [
                    'Amount',
                    sprintf(
                        '%d %s',
                        $order->final_amount,
                        $order->currency,
                    ),
                ],
                [
                    'Linked Server',
                    $order->server_id !== null
                        ? (string) $order->server_id
                        : 'None',
                ],
            ],
        );
    }

    private function displayResult(
        ?Order $order,
        Server $server,
    ): void {
        $this->table(
            [
                'Field',
                'Value',
            ],
            [
                [
                    'Order ID',
                    $order instanceof Order
                        ? (string) $order->getKey()
                        : 'Unknown',
                ],
                [
                    'Order Status',
                    $order instanceof Order
                        ? $order->status->value
                        : 'Unknown',
                ],
                [
                    'xDeploy Server ID',
                    (string) $server->getKey(),
                ],
                [
                    'Provider Server ID',
                    (string) $server->cloud_server_id,
                ],
                [
                    'Provider',
                    (string) $server->cloud_provider,
                ],
                [
                    'Region',
                    (string) $server->cloud_region,
                ],
                [
                    'Host',
                    (string) $server->host,
                ],
                [
                    'Status',
                    $server->status->value,
                ],
            ],
        );
    }
}
