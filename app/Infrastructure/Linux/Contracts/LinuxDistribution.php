<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Contracts;

interface LinuxDistribution
{
    public function hostname(): string;

    public function operatingSystem(): string;

    public function kernel(): string;

    public function whoami(): string;

    public function uptime(): string;

    public function privateIp(): string;

    public function cpu(): string;

    public function memory(): string;

    public function disk(): string;

    public function loadAverage(): string;

    public function services(): string;
}
