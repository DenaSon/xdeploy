<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Applications\Operations;

use App\Application\Applications\Operations\RunApplicationOperationJob;
use App\Support\SSH\SSHTimeout;
use Tests\TestCase;

final class ApplicationOperationQueueConfigurationTest extends TestCase
{
    public function test_timeout_windows_are_ordered_to_prevent_duplicate_provisioning(): void
    {
        $job = new RunApplicationOperationJob(1);

        self::assertGreaterThan(
            1800,
            SSHTimeout::DOCKER_INSTALL,
        );

        self::assertGreaterThan(
            SSHTimeout::DOCKER_INSTALL,
            $job->timeout,
        );

        self::assertGreaterThan(
            $job->timeout,
            (int) config(
                'queue.connections.database.retry_after',
            ),
        );

        self::assertSame(
            1,
            $job->tries,
        );

        self::assertTrue(
            $job->failOnTimeout,
        );
    }
}
