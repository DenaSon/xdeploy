<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

interface InstallableInterface
{
    public function install(): void;

    public function uninstall(): void;
}
