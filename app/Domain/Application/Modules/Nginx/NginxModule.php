<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Nginx;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\Exceptions\ModuleInstallationException;
use App\Domain\Module\Exceptions\ModuleStartException;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;

final readonly class NginxModule extends CommandModule implements StartableInterface
{
    public function type(): ModuleType
    {
        return ModuleType::Nginx;
    }

    public function name(): string
    {
        return 'Nginx';
    }

    protected function inspectCommand(): string
    {
        return 'nginx -v 2>&1';
    }

    protected function resolveState(): ModuleState
    {
        $runtime = $this->ssh->executeWithResult(
            'systemctl is-active nginx',
        );

        if (
            $runtime->successful()
            && trim($runtime->output) === 'active'
        ) {
            return ModuleState::Running;
        }

        $installed = $this->ssh->executeWithResult(
            'nginx -v 2>&1',
        );

        return $installed->successful()
            ? ModuleState::Installed
            : ModuleState::NotInstalled;
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
            new ProvidedSoftware(
                SoftwareType::Nginx,
            ),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
DEBIAN_FRONTEND=noninteractive apt-get update &&
DEBIAN_FRONTEND=noninteractive apt-get install -y nginx
BASH;
    }

    protected function checkRequirements(): void
    {
        $apache = $this->ssh->executeWithResult(
            'systemctl is-active apache2',
        );

        if (
            $apache->successful()
            && trim($apache->output) === 'active'
        ) {
            throw new ModuleInstallationException(
                'Cannot install Nginx: Apache2 service is running and occupies port 80.',
            );
        }
    }

    public function start(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl start nginx',
        );

        if (! $result->successful()) {
            throw new ModuleStartException(
                'Failed to start Nginx.',
            );
        }

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleStartException(
                'Nginx did not enter running state.',
            );
        }
    }

    public function stop(): void
    {
        $this->ssh->executeWithResult(
            'systemctl stop nginx',
        );
    }

    public function restart(): void
    {
        $this->ssh->executeWithResult(
            'systemctl restart nginx',
        );
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DEFAULT;
    }
}
