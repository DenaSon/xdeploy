<?php

namespace App\Support\SSH;

final class SSHTimeout
{
    private function __construct() {}

    public const int DEFAULT = 60;

    public const int QUICK = 10;

    public const int NORMAL = 30;

    public const int DOCKER_INSTALL = 120;

    public const int APPLICATION_INSTALL = 90;

    public const int IMAGE_PULL = 180;
}
