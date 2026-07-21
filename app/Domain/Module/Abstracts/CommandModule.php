<?php

declare(strict_types=1);

namespace App\Domain\Module\Abstracts;

use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Exceptions\ModuleInstallationException;
use App\Support\SSH\SSHTimeout;

abstract readonly class CommandModule extends AbstractModule
{
    final public function inspect(): ModuleInfo
    {
        $result = $this->ssh->executeWithResult(
            $this->inspectCommand(),
        );

        if (! $result->successful()) {
            return $this->moduleInfo(ModuleState::NotInstalled);
        }

        return $this->moduleInfo(
            state: $this->resolveState(),
            metadata: $this->metadataFromOutput($result->output),
        );
    }

    final public function install(): void
    {
        $result = $this->ssh->executeWithResult(
            $this->installCommand(),
            $this->installTimeout(),
        );

        if (! $result->successful()) {
            throw new ModuleInstallationException('Module Installation failed');
        }
    }

    protected function resolveState(): ModuleState
    {
        return ModuleState::Installed;
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    protected function moduleInfo(
        ModuleState $state,
        array $metadata = [],
    ): ModuleInfo {
        return new ModuleInfo(
            state: $state,
            metadata: $metadata,
            dependencies: $this->dependencies(),
            provides: $this->provides(),
        );
    }

    abstract protected function inspectCommand(): string;

    abstract protected function installCommand(): string;

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
