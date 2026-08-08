<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\CpuInfoData;
use App\Domain\Server\DTOs\DiskInfoData;
use App\Domain\Server\DTOs\DockerRuntimeData;
use App\Domain\Server\DTOs\LoadAverageData;
use App\Domain\Server\DTOs\MemoryInfoData;
use App\Domain\Server\DTOs\SystemServiceData;
use App\Domain\Server\Parsers\Contracts\Parser;
use App\Domain\Server\Parsers\CpuParser;
use App\Domain\Server\Parsers\DiskParser;
use App\Domain\Server\Parsers\LoadAverageParser;
use App\Domain\Server\Parsers\MemoryParser;
use App\Domain\Server\Parsers\SystemServiceParser;
use App\Infrastructure\Docker\Services\DockerInspector;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

final readonly class ServerInspector
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private LinuxDistribution $distribution,
        private MemoryParser $memoryParser,
        private CpuParser $cpuParser,
        private DiskParser $diskParser,
        private LoadAverageParser $loadAverageParser,
        private SystemServiceParser $systemServiceParser,
        private DockerInspector $dockerInspector,
    ) {}

    public function hostname(): string
    {
        return $this->run(
            $this->distribution->hostname(),
        );
    }

    public function operatingSystem(): string
    {
        return $this->run(
            $this->distribution->operatingSystem(),
        );
    }

    public function kernel(): string
    {
        return $this->run(
            $this->distribution->kernel(),
        );
    }

    public function whoami(): string
    {
        return $this->run(
            $this->distribution->whoami(),
        );
    }

    public function uptime(): string
    {
        return $this->run(
            $this->distribution->uptime(),
        );
    }

    public function privateIp(): string
    {
        return $this->run(
            $this->distribution->privateIp(),
        );
    }

    public function cpu(): CpuInfoData
    {
        /** @var CpuInfoData */
        return $this->parse(
            $this->distribution->cpu(),
            $this->cpuParser,
        );
    }

    public function memory(): MemoryInfoData
    {
        /** @var MemoryInfoData */
        return $this->parse(
            $this->distribution->memory(),
            $this->memoryParser,
        );
    }

    public function disk(): DiskInfoData
    {
        /** @var DiskInfoData */
        return $this->parse(
            $this->distribution->disk(),
            $this->diskParser,
        );
    }

    public function loadAverage(): LoadAverageData
    {
        /** @var LoadAverageData */
        return $this->parse(
            $this->distribution->loadAverage(),
            $this->loadAverageParser,
        );
    }

    /**
     * @return list<SystemServiceData>
     */
    public function services(): array
    {
        return $this->systemServiceParser
            ->parse(
                $this->run(
                    $this->distribution->services(),
                ),
            );
    }

    public function docker(): DockerRuntimeData
    {
        return $this->dockerInspector
            ->inspect();
    }

    private function run(
        string $command,
    ): string {
        return trim(
            $this->ssh->execute(
                $command,
            ),
        );
    }

    private function parse(
        string $command,
        Parser $parser,
    ): mixed {
        return $parser->parse(
            $this->run(
                $command,
            ),
        );
    }
}
