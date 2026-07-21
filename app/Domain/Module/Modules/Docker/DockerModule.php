<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Docker;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\Exceptions\ModuleStartException;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;
use LogicException;

final readonly class DockerModule extends CommandModule implements StartableInterface
{
    public function type(): ModuleType
    {
        return ModuleType::Docker;
    }

    public function name(): string
    {
        return 'Docker';
    }

    protected function inspectCommand(): string
    {
        return 'docker --version';
    }

    protected function resolveState(): ModuleState
    {
        $result = $this->ssh->executeWithResult(
            'systemctl is-active docker',
        );

        return $result->successful() && trim($result->output) === 'active'
            ? ModuleState::Running
            : ModuleState::Installed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        preg_match('/\d+\.\d+\.\d+/', $output, $matches);

        return [
            'version' => $matches[0] ?? null,
        ];
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::Docker),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
curl -fsSL https://get.docker.com | sh
BASH;
    }

    public function start(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl start docker',
        );

        if (! $result->successful()) {
            throw new ModuleStartException(
                'Failed to start Docker.',
            );
        }

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleStartException(
                'Docker did not enter the running state.',
            );
        }
    }

    public function stop(): void
    {
        throw new LogicException('Not implemented.');
    }

    public function restart(): void
    {
        throw new LogicException('Not implemented.');
    }

    public function uninstall(): void
    {
        throw new LogicException('Not implemented.');
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DOCKER_INSTALL;
    }

}
