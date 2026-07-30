<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Abstracts;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\ValueObjects\ApplicationDependency;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

abstract readonly class AbstractApplication implements ApplicationInterface
{
    public function __construct(
        protected SSHConnectionInterface $ssh,
    ) {}

    abstract public function type(): ApplicationType;

    abstract public function name(): string;

    /**
     * @return list<ApplicationDependency>
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
     * Override if the application has installation prerequisites.
     */
    protected function checkRequirements(): void
    {
        //
    }

    /**
     * Hook executed before installation.
     * Override if the application requires preparation.
     */
    public function prepare(): void
    {
        //
    }

    /**
     * Hook executed after installation.
     * Override if the application requires additional configuration.
     */
    public function configure(): void
    {
        //
    }

    /**
     * Hook executed after installation/configuration.
     * Override if the application supports health checks.
     */
    public function healthCheck(): void
    {
        //
    }

    abstract public function install(): void;

    abstract public function uninstall(): void;
}
