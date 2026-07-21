<?php

namespace App\Infrastructure\Linux\Distributions;

use App\Infrastructure\Linux\Contracts\LinuxDistribution;

final class UbuntuDistribution implements LinuxDistribution
{
    /**
     * Get the hostname command.
     */
    public function hostname(): string
    {
        return 'hostname';
    }

    /**
     * Get the operating system command.
     */
    public function operatingSystem(): string
    {
        return 'cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'';
    }

    /**
     * Get the Linux kernel version command.
     */
    public function kernel(): string
    {
        return 'uname -r';
    }

    /**
     * Get the current SSH user command.
     */
    public function whoami(): string
    {
        return 'whoami';
    }

    /**
     * Get the server uptime command.
     */
    public function uptime(): string
    {
        return 'uptime -p';
    }

    /**
     * Get the primary private IPv4 address command.
     */
    public function privateIp(): string
    {
        return "hostname -I | awk '{print \$1}'";
    }

    /**
     * Get CPU information command.
     */
    public function cpu(): string
    {
        return 'lscpu';
    }

    /**
     * Get memory information command.
     */
    public function memory(): string
    {
        return 'free --bytes';
    }

    /**
     * Get disk usage command.
     */
    public function disk(): string
    {
        return 'df --block-size=1 --output=size,used,avail,pcent,target /';
    }

    /**
     * Get system load average command.
     */
    public function loadAverage(): string
    {
        return 'cat /proc/loadavg';
    }

    /**
     * Get service status command.
     */
    /**
     * Get service status command.
     */
    /**
     * Get service status command.
     */
    public function serviceStatus(string $service): string
    {
        return <<<BASH
if systemctl show "{$service}" >/dev/null 2>&1; then
    systemctl is-active "{$service}"
else
    echo "not-installed"
fi
BASH;
    }
}
