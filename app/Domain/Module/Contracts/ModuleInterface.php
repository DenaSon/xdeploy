<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\ValueObjects\ModuleDependency;
use App\Domain\Module\ValueObjects\ProvidedSoftware;

interface ModuleInterface extends InstallableInterface, InspectableInterface
{
    /**
     * Returns the unique type of the module.
     */
    public function type(): ModuleType;

    /**
     * Returns the display name of the module.
     */
    public function name(): string;

    /**
     * Returns the module dependencies.
     *
     * @return list<ModuleDependency>
     */
    public function dependencies(): array;

    /**
     * Returns the software provided by this module.
     *
     * @return list<ProvidedSoftware>
     */
    public function provides(): array;
}
