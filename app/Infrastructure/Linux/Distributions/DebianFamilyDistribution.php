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
        return "hostname -I | awk '{print \$1}'";
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

    public function serviceStatus(
        string $service,
    ): string {
        return <<<BASH
if systemctl show "{$service}" >/dev/null 2>&1; then
    systemctl is-active "{$service}"
else
    echo "not-installed"
fi
BASH;
    }

    public function dockerContainerStatus(
        string $container,
    ): string {
        return <<<BASH
if docker ps \
    --filter "name={$container}" \
    --filter "status=running" \
    --format "{{.Names}}" | grep -q .; then
    echo "active"
else
    echo "inactive"
fi
BASH;
    }
}
