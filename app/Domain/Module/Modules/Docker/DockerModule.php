<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Docker;

use App\Domain\Module\Contracts\Inspectable;
use App\Domain\Module\Contracts\Module;
use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

final readonly class DockerModule implements Inspectable, Module
{
    public function __construct(
        private readonly SSHConnectionInterface $ssh,
        private readonly LinuxDistribution $distribution,
    ) {}

    public function type(): ModuleType
    {
        return ModuleType::Docker;
    }

    public function name(): string
    {
        return 'Docker';
    }

    public function inspect(): ModuleInfo
    {
        $exists = $this->ssh->executeWithResult(
            $this->existsCommand()
        );

        if (! $exists->successful()) {
            return new ModuleInfo(
                state: ModuleState::NotInstalled,
            );
        }

        $version = $this->ssh->executeWithResult(
            $this->versionCommand()
        );

        return new ModuleInfo(
            state: ModuleState::Installed,
            metadata: [
                'version' => $this->parseVersion($version->output),
            ],
        );
    }

    private function existsCommand(): string
    {
        return 'command -v docker >/dev/null 2>&1';
    }

    private function versionCommand(): string
    {
        return 'docker --version';
    }

    private function parseVersion(string $output): string
    {
        preg_match('/\d+\.\d+\.\d+/', $output, $matches);

        return $matches[0] ?? '';
    }
}
