<?php

declare(strict_types=1);

namespace App\Domain\Application\Registry;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use RuntimeException;

final readonly class ApplicationRegistry implements ApplicationRegistryInterface
{
    /**
     * @param  array<int, ApplicationInterface>  $modules
     */
    public function __construct(
        private array $modules,
    ) {}

    /**
     * @return array<int, ApplicationInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(ApplicationType $type): ApplicationInterface
    {
        foreach ($this->modules as $module) {
            if ($module->type() === $type) {
                return $module;
            }
        }

        throw new RuntimeException(sprintf(
            'Module [%s] is not registered.',
            $type->value,
        ));
    }
}
