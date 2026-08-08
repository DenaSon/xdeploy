<?php

declare(strict_types=1);

namespace App\Application\Billing\Jobs;

use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProvisionPaidOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Cloud provisioning is a billable external side effect.
     *
     * Never allow Laravel to blindly retry the whole workflow.
     * Recovery is handled explicitly by ProvisionPaidOrderAction.
     */
    public int $tries = 1;

    /**
     * This job owns only the billable provider-delivery phase.
     * SSH readiness is dispatched separately after fulfillment.
     */
    public int $timeout = 900;

    /**
     * A timeout must be treated as a failed job instead of being
     * silently released for another automatic attempt.
     */
    public bool $failOnTimeout = true;

    /**
     * Prevent duplicate dispatches for the same Order while a
     * provisioning job is queued/running. The domain/application
     * state machine remains the authoritative second line of defense.
     */
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $orderId,
    ) {
        $this->onQueue(
            'provisioning',
        );
    }

    public function uniqueId(): string
    {
        return sprintf(
            'order:%d',
            $this->orderId,
        );
    }

    public function handle(
        ProvisionPaidOrderAction $action,
    ): void {
        $server = $action->execute(
            $this->orderId,
        );

        VerifyProvisionedServerReadinessJob::dispatch(
            $server->getKey(),
        );
    }
}
