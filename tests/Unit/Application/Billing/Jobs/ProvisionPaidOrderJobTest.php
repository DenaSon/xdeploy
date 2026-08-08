<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing\Jobs;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

final class ProvisionPaidOrderJobTest extends TestCase
{
    public function test_it_is_a_unique_single_attempt_provisioning_job(): void
    {
        $job = new ProvisionPaidOrderJob(
            orderId: 42,
        );

        $this->assertInstanceOf(
            ShouldQueue::class,
            $job,
        );

        $this->assertInstanceOf(
            ShouldBeUnique::class,
            $job,
        );

        $this->assertSame(
            42,
            $job->orderId,
        );

        $this->assertSame(
            'order:42',
            $job->uniqueId(),
        );

        $this->assertSame(
            1,
            $job->tries,
        );

        $this->assertSame(
            900,
            $job->timeout,
        );

        $this->assertTrue(
            $job->failOnTimeout,
        );

        $this->assertSame(
            1800,
            $job->uniqueFor,
        );

        $this->assertSame(
            'provisioning',
            $job->queue,
        );
    }
}
