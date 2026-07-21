<?php
declare(strict_types=1);

namespace App\Domain\Module\Contracts;

interface InstallableInterface
{
    public function install(): void;

    public function uninstall(): void;
}
