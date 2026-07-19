<?php

namespace App\Domain\Server\Support;

final class LinuxCommands
{
    /**
     * Get server hostname.
     */
    public const string HOSTNAME = 'hostname';

    /**
     * Get current SSH user.
     */
    public const string WHOAMI = 'whoami';

    /**
     * Get Linux kernel version.
     */
    public const string KERNEL = 'uname -r';

    /**
     * Get server uptime.
     */
    public const string UPTIME = 'uptime -p';

    /**
     * Get operating system name.
     */
    public const string OPERATING_SYSTEM =
        "cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d '\"'";

    /**
     * Get private IPv4 address.
     */
    public static function privateIp(): string
    {
        return "hostname -I | awk '{print \$1}'";
    }

    /**
     * Get CPU information.
     */
    public static function cpu(): string
    {
        return 'lscpu';
    }

    /**
     * Get memory information.
     */
    public static function memory(): string
    {
        return 'free -b';
    }

    /**
     * Get disk usage.
     */
    public static function disk(): string
    {
        return 'df -B1 /';
    }

    /**
     * Get system load average.
     */
    public static function loadAverage(): string
    {
        return 'cat /proc/loadavg';
    }
}
