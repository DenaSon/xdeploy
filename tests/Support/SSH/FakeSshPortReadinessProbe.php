<?php

declare(strict_types=1);

namespace Tests\Support\SSH;

use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use App\Models\Server;

final class FakeSshPortReadinessProbe implements SSHPortReadinessProbeInterface
{
    private bool $ready = true;

    private int $attempts = 0;

    public function waitUntilReady(
        Server $server,
    ): bool {
        $this->attempts++;

        return $this->ready;
    }

    public function ready(): self
    {
        $this->ready = true;

        return $this;
    }

    public function unavailable(): self
    {
        $this->ready = false;

        return $this;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function reset(): self
    {
        $this->ready = true;
        $this->attempts = 0;

        return $this;
    }
}
