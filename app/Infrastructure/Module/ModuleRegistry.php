<?php

declare(strict_types=1);

namespace App\Infrastructure\Module;

use App\Domain\Module\Contracts\Module;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Modules\Docker\DockerModule;

final readonly class ModuleRegistry implements ModuleRegistryInterface
{
    /**
     * @param  array<Module>  $modules
     */
    public function __construct(
        private array $modules,
    ) {}

    /**
     * @return array<Module>
     */
    public function all(): array
    {
        return $this->modules;
    }
}
