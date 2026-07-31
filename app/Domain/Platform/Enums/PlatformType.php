<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

enum PlatformType: string
{
    case Docker = 'docker';

    case DockerCompose = 'docker-compose';
}
