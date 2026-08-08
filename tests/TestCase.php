<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\SSH\FakeSshPortReadinessProbe;

abstract class TestCase extends BaseTestCase
{
    private FakeSshPortReadinessProbe $sshPortReadinessProbe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sshPortReadinessProbe =
            new FakeSshPortReadinessProbe;

        $this->app->instance(
            SSHPortReadinessProbeInterface::class,
            $this->sshPortReadinessProbe,
        );
    }

    protected function sshPortReadinessProbe(): FakeSshPortReadinessProbe
    {
        return $this->sshPortReadinessProbe;
    }
}
