<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\CpuInfoData;
use App\Domain\Server\DTOs\DockerRuntimeData;
use App\Domain\Server\DTOs\ResourceUsageData;
use App\Domain\Server\DTOs\ServerIdentityData;
use App\Domain\Server\DTOs\ServerOverviewData;
use App\Domain\Server\DTOs\SystemServiceData;

final readonly class ServerService
{
    public function __construct(
        private ServerInspector $inspector,
    ) {}

    /**
     * Backward-compatible aggregate snapshot used by the current Dashboard.
     */
    public function overview(): ServerOverviewData
    {
        $identity = $this->identity();
        $resources = $this->resourceUsage();

        return new ServerOverviewData(
            hostname: $identity->hostname,
            operatingSystem: $identity->operatingSystem,
            kernel: $identity->kernel,
            uptime: $identity->uptime,
            user: $identity->user,
            privateIp: $identity->privateIp,
            cpu: $this->cpu(),
            memory: $resources->memory,
            disk: $resources->disk,
            loadAverage: $resources->loadAverage,
            services: $this->services(),
            docker: $this->docker(),
        );
    }

    public function identity(): ServerIdentityData
    {
        return new ServerIdentityData(
            hostname: $this->inspector->hostname(),
            operatingSystem: $this->inspector->operatingSystem(),
            kernel: $this->inspector->kernel(),
            uptime: $this->inspector->uptime(),
            user: $this->inspector->whoami(),
            privateIp: $this->inspector->privateIp(),
        );
    }

    public function cpu(): CpuInfoData
    {
        return $this->inspector->cpu();
    }

    public function resourceUsage(): ResourceUsageData
    {
        return new ResourceUsageData(
            memory: $this->inspector->memory(),
            disk: $this->inspector->disk(),
            loadAverage: $this->inspector->loadAverage(),
        );
    }

    /**
     * @return list<SystemServiceData>
     */
    public function services(): array
    {
        return $this->inspector->services();
    }

    public function docker(): DockerRuntimeData
    {
        return $this->inspector->docker();
    }
}
