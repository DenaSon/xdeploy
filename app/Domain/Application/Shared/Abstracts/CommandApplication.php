<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Abstracts;

use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Exceptions\ApplicationInstallationException;
use App\Domain\Application\Shared\Exceptions\ApplicationUninstallException;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

abstract readonly class CommandApplication extends AbstractApplication
{
    final public function inspect(): ApplicationInfo
    {
        try {
            $result = $this->ssh->executeWithResult(
                command: $this->inspectCommand(),
                timeout: $this->inspectTimeout(),
            );

            if (! $result->successful()) {
                return $this->applicationInfo(
                    ApplicationState::NotInstalled,
                );
            }

            return $this->applicationInfo(
                state: $this->resolveState(),
                metadata: $this->metadataFromOutput(
                    $result->output,
                ),
            );
        } catch (RuntimeException) {
            return $this->applicationInfo(
                ApplicationState::Unknown,
            );
        }
    }

    final public function install(): void
    {
        $this->checkRequirements();

        $this->prepare();

        $result = $this->ssh->executeWithResult(
            command: $this->installCommand(),
            timeout: $this->installTimeout(),
        );

        if (! $result->successful()) {
            throw new ApplicationInstallationException(
                sprintf(
                    'Application [%s] installation command failed.',
                    $this->type()->value,
                ),
            );
        }

        $this->configure();
    }

    final public function uninstall(): void
    {
        $result = $this->ssh->executeWithResult(
            command: $this->uninstallCommand(),
            timeout: $this->uninstallTimeout(),
        );

        if (! $result->successful()) {
            throw new ApplicationUninstallException(
                sprintf(
                    'Application [%s] uninstall command failed.',
                    $this->type()->value,
                ),
            );
        }
    }

    protected function resolveState(): ApplicationState
    {
        return ApplicationState::Installed;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function applicationInfo(
        ApplicationState $state,
        array $metadata = [],
    ): ApplicationInfo {
        return new ApplicationInfo(
            state: $state,
            metadata: $metadata,
            requirements: $this->requirements(),
            provides: $this->provides(),
        );
    }

    abstract protected function inspectCommand(): string;

    abstract protected function installCommand(): string;

    protected function uninstallCommand(): string
    {
        throw new ApplicationUninstallException(
            sprintf(
                'Uninstall is not supported for application [%s].',
                $this->type()->value,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function metadataFromOutput(
        string $output,
    ): array;

    protected function inspectTimeout(): int
    {
        return SSHTimeout::QUICK;
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DEFAULT;
    }

    protected function uninstallTimeout(): int
    {
        return SSHTimeout::DEFAULT;
    }
}
