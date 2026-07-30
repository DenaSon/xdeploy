<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Abstracts;

use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Exceptions\ApplicationInstallationException;
use App\Domain\Application\Shared\Exceptions\ApplicationUninstallException;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Support\SSH\SSHTimeout;

abstract readonly class CommandApplication extends AbstractApplication
{
    final public function inspect(): ApplicationInfo
    {
        $result = $this->ssh->executeWithResult(
            $this->inspectCommand(),
        );

        if (! $result->successful()) {
            return $this->moduleInfo(ApplicationState::NotInstalled);
        }

        return $this->moduleInfo(
            state: $this->resolveState(),
            metadata: $this->metadataFromOutput($result->output),
        );
    }

    final public function install(): void
    {
        $this->checkRequirements();

        $this->prepare();

        $result = $this->ssh->executeWithResult(
            $this->installCommand(),
            $this->installTimeout(),
        );

        if (! $result->successful()) {
            throw new ApplicationInstallationException(
                'Module Installation failed',
            );
        }

        $this->configure();

        $this->healthCheck();
    }

    final public function uninstall(): void
    {
        $result = $this->ssh->executeWithResult(
            $this->uninstallCommand(),
            $this->installTimeout(),
        );

        if (! $result->successful()) {
            throw new ApplicationUninstallException(
                'Module uninstall failed.',
            );
        }
    }

    protected function resolveState(): ApplicationState
    {
        return ApplicationState::Installed;
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    protected function moduleInfo(
        ApplicationState $state,
        array $metadata = [],
    ): ApplicationInfo {
        return new ApplicationInfo(
            state: $state,
            metadata: $metadata,
            dependencies: $this->dependencies(),
            provides: $this->provides(),
        );
    }

    abstract protected function inspectCommand(): string;

    abstract protected function installCommand(): string;

    protected function uninstallCommand(): string
    {
        throw new ApplicationUninstallException(
            'Uninstall is not supported for this module.',
        );
    }

    /**
     * @return array<string,mixed>
     */
    abstract protected function metadataFromOutput(
        string $output,
    ): array;

    protected function installTimeout(): int
    {
        return SSHTimeout::DEFAULT;
    }
}
