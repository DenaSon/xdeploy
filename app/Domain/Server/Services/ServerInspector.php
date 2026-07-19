<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\CpuInfoData;
use App\Domain\Server\DTOs\DiskInfoData;
use App\Domain\Server\DTOs\LoadAverageData;
use App\Domain\Server\DTOs\MemoryInfoData;
use App\Domain\Server\Parsers\Contracts\Parser;
use App\Domain\Server\Parsers\CpuParser;
use App\Domain\Server\Parsers\DiskParser;
use App\Domain\Server\Parsers\LoadAverageParser;
use App\Domain\Server\Parsers\MemoryParser;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

readonly class ServerInspector
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private LinuxDistribution $distribution,
        private MemoryParser $memoryParser,
        private CpuParser $cpuParser,
        private DiskParser $diskParser,
        private LoadAverageParser $loadAverageParser,
    ) {}

    /**
     * Execute an SSH command.
     */
    private function run(string $command): string
    {
        return trim(
            $this->ssh->execute($command)
        );
    }

    /**
     * Execute an SSH command and parse its output.
     */
    private function parse(string $command, Parser $parser): mixed
    {
        return $parser->parse(
            $this->run($command)
        );
    }

    /**
     * Get server hostname.
     */
    public function hostname(): string
    {
        return $this->run(
            $this->distribution->hostname()
        );
    }

    /**
     * Get operating system.
     */
    public function operatingSystem(): string
    {
        return $this->run(
            $this->distribution->operatingSystem()
        );
    }

    /**
     * Get Linux kernel version.
     */
    public function kernel(): string
    {
        return $this->run(
            $this->distribution->kernel()
        );
    }

    /**
     * Get current SSH user.
     */
    public function whoami(): string
    {
        return $this->run(
            $this->distribution->whoami()
        );
    }

    /**
     * Get server uptime.
     */
    public function uptime(): string
    {
        return $this->run(
            $this->distribution->uptime()
        );
    }

    /**
     * Get primary private IP address.
     */
    public function privateIp(): string
    {
        return $this->run(
            $this->distribution->privateIp()
        );
    }

    /**
     * Get CPU information.
     */
    public function cpu(): CpuInfoData
    {
        return $this->parse(
            $this->distribution->cpu(),
            $this->cpuParser,
        );
    }

    /**
     * Get memory information.
     */
    public function memory(): MemoryInfoData
    {
        return $this->parse(
            $this->distribution->memory(),
            $this->memoryParser,
        );
    }

    /**
     * Get disk usage information.
     */
    public function disk(): DiskInfoData
    {
        return $this->parse(
            $this->distribution->disk(),
            $this->diskParser,
        );
    }

    /**
     * Get system load average.
     */
    public function loadAverage(): LoadAverageData
    {
        return $this->parse(
            $this->distribution->loadAverage(),
            $this->loadAverageParser,
        );
    }

    /**
     * Get service status.
     */
    public function serviceStatus(string $service): string
    {
        return $this->run(
            $this->distribution->serviceStatus($service)
        );
    }
}
