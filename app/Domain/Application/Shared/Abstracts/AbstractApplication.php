<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Abstracts;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

abstract readonly class AbstractApplication implements ApplicationInterface
{
    public function __construct(
        protected SSHConnectionInterface $ssh,
    ) {}

    abstract public function type(): ApplicationType;

    abstract public function name(): string;

    public function requirements(): ApplicationRequirements
    {
        return new ApplicationRequirements;
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [];
    }

    protected function checkRequirements(): void
    {
        //
    }

    protected function prepare(): void
    {
        //
    }

    protected function configure(): void
    {
        //
    }

    abstract public function install(): void;

    abstract public function uninstall(): void;
}
