<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Redis;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\Exceptions\ModuleStartException;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;

final readonly class RedisModule extends CommandModule implements StartableInterface
{
    public function type(): ModuleType
    {
        return ModuleType::Redis;
    }

    public function name(): string
    {
        return 'Redis';
    }

    protected function inspectCommand(): string
    {
        return 'redis-server --version';
    }

    protected function resolveState(): ModuleState
    {
        $runtime = $this->ssh->executeWithResult(
            'systemctl is-active redis-server',
        );

        if (
            $runtime->successful()
            && trim($runtime->output) === 'active'
        ) {
            return ModuleState::Running;
        }

        $installed = $this->ssh->executeWithResult(
            'redis-server --version',
        );

        return $installed->successful()
            ? ModuleState::Installed
            : ModuleState::NotInstalled;
    }

    /**
     * @return array<string,mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        preg_match('/v=(\d+\.\d+\.\d+)/', $output, $matches);

        return [
            'version' => $matches[1] ?? null,
        ];
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [
            new ProvidedSoftware(
                SoftwareType::Redis,
            ),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
DEBIAN_FRONTEND=noninteractive apt-get update &&
DEBIAN_FRONTEND=noninteractive apt-get install -y redis-server
BASH;
    }

    public function start(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl start redis-server',
        );

        if (! $result->successful()) {
            throw new ModuleStartException(
                'Failed to start Redis.',
            );
        }

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleStartException(
                'Redis did not enter running state.',
            );
        }
    }

    public function stop(): void
    {
        $this->ssh->executeWithResult(
            'systemctl stop redis-server',
        );
    }

    public function restart(): void
    {
        $this->ssh->executeWithResult(
            'systemctl restart redis-server',
        );
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DEFAULT;
    }
}
