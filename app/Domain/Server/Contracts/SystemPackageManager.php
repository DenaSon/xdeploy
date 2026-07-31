<?php

declare(strict_types=1);

namespace App\Domain\Server\Contracts;

interface SystemPackageManager
{
    /**
     * Determine whether a system package is installed.
     */
    public function isInstalled(string $package): bool;

    /**
     * Install the given system packages.
     *
     * @param  list<string>  $packages
     */
    public function install(array $packages): void;
}
