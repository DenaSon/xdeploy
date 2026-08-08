<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Distributions;

use App\Infrastructure\Linux\Contracts\LinuxDistribution;

final readonly class DebianFamilyDistribution implements LinuxDistribution
{
    public function hostname(): string
    {
        return 'hostname';
    }

    public function operatingSystem(): string
    {
        return 'cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'';
    }

    public function kernel(): string
    {
        return 'uname -r';
    }

    public function whoami(): string
    {
        return 'whoami';
    }

    public function uptime(): string
    {
        return 'uptime -p';
    }

    public function privateIp(): string
    {
        return <<<'BASH'
hostname -I | awk '{print $1}'
BASH;
    }

    public function cpu(): string
    {
        return 'lscpu';
    }

    public function memory(): string
    {
        return 'free --bytes';
    }

    public function disk(): string
    {
        return 'df --block-size=1 --output=size,used,avail,pcent,target /';
    }

    public function loadAverage(): string
    {
        return 'cat /proc/loadavg';
    }

    public function services(): string
    {
        return <<<'BASH'
systemctl list-units \
    --type=service \
    --all \
    --no-pager \
    --no-legend \
    --plain
BASH;
    }
}
