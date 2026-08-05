<?php

declare(strict_types=1);

namespace App\Support\SSH;

final class SSHTimeout
{
    /**
     * Establishing the TCP/SSH connection.
     */
    public const int CONNECTION = 20;

    /**
     * SSH authentication handshake.
     */
    public const int AUTHENTICATION = 40;

    /**
     * Fast inspection commands.
     */
    public const int QUICK = 20;

    /**
     * Normal remote commands.
     */
    public const int NORMAL = 30;

    /**
     * Default command timeout.
     */
    public const int DEFAULT = 60;

    /**
     * Long-running application installers.
     */
    public const int APPLICATION_INSTALL = 600;

    /**
     * Docker installation.
     */
    public const int DOCKER_INSTALL = 400;

    /**
     * Operating-system package installation.
     */
    public const int SYSTEM_PACKAGE_INSTALL = 180;

    /**
     * Docker image pull operations.
     */
    public const int IMAGE_PULL = 180;

    const int APPLICATION_UNINSTALL = 35;

    private function __construct() {}
}
