<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing\Jobs;

use App\Application\Billing\Jobs\VerifyProvisionedServerReadinessJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

final class VerifyProvisionedServerReadinessJobTest extends TestCase
{
    public function test_it_is_a_unique_non_billable_readiness_job(): void
    {
        $job = new VerifyProvisionedServerReadinessJob(
            serverId: 27,
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
            27,
            $job->serverId,
        );

        $this->assertSame(
            'server-readiness:27',
            $job->uniqueId(),
        );

        $this->assertSame(
            1,
            $job->tries,
        );

        $this->assertSame(
            180,
            $job->timeout,
        );

        $this->assertTrue(
            $job->failOnTimeout,
        );

        $this->assertSame(
            'provisioning',
            $job->queue,
        );
    }
}
