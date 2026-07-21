<?php

declare(strict_types=1);

namespace App\Domain\Module\Abstracts;

use App\Domain\Module\Contracts\ModuleInterface;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\ValueObjects\ModuleDependency;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

abstract readonly class AbstractModule implements ModuleInterface
{
    public function __construct(
        protected SSHConnectionInterface $ssh,
    ) {}

    abstract public function type(): ModuleType;

    abstract public function name(): string;

    /**
     * @return list<ModuleDependency>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Hook executed before installation.
     * Override if the module has installation prerequisites.
     */
    protected function checkRequirements(): void
    {
        //
    }

    /**
     * Hook executed before installation.
     * Override if the module requires any preparation.
     */
    public function prepare(): void
    {
        //
    }

    /**
     * Hook executed after installation.
     * Override if the module requires additional configuration.
     */
    public function configure(): void
    {
        //
    }

    /**
     * Hook executed after installation/configuration.
     * Override if the module supports health checks.
     */
    public function healthCheck(): void
    {
        //
    }

    abstract public function install(): void;

    abstract public function uninstall(): void;
}
