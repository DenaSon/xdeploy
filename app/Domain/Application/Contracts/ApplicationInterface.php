<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;

interface ApplicationInterface extends InspectableInterface, InstallableInterface
{
    /**
     * Returns the unique application type.
     */
    public function type(): ApplicationType;

    /**
     * Returns the application display name.
     */
    public function name(): string;

    /**
     * Returns the requirements needed by the application.
     */
    public function requirements(): ApplicationRequirements;

    /**
     * Returns the software provided by the application.
     *
     * @return list<ProvidedSoftware>
     */
    public function provides(): array;
}
