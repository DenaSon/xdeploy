<?php
declare(strict_types=1);

namespace App\Domain\Module\Contracts;

interface ModuleRegistryInterface
{
    /**
     * @return array<Module>
     */
    public function all(): array;
}
