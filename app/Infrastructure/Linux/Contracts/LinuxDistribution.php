<?php

namespace App\Infrastructure\Linux\Contracts;

interface LinuxDistribution
{
    /**
     * Get the hostname command.
     */
    public function hostname(): string;

    /**
     * Get the operating system command.
     */
    public function operatingSystem(): string;

    /**
     * Get the Linux kernel version command.
     */
    public function kernel(): string;

    /**
     * Get the current SSH user command.
     */
    public function whoami(): string;

    /**
     * Get the server uptime command.
     */
    public function uptime(): string;

    /**
     * Get the primary private IPv4 address command.
     */
    public function privateIp(): string;

    /**
     * Get the CPU information command.
     */
    public function cpu(): string;

    /**
     * Get the memory information command.
     */
    public function memory(): string;

    /**
     * Get the disk usage command.
     */
    public function disk(): string;

    /**
     * Get the system load average command.
     */
    public function loadAverage(): string;

    /**
     * Get service status command.
     */
    public function serviceStatus(string $service): string;
}
