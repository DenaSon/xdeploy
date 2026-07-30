<?php

declare(strict_types=1);

namespace App\Domain\Application\Enums;

enum ModuleType: string
{
    case Docker = 'docker';
    case Nginx = 'nginx';
    case Marzban = 'marzban';
    case Xray = 'xray';
    case Fail2Ban = 'fail2ban';
    case Php = 'php';

    case Composer = 'composer';
    case Git = 'git';
    case Redis = 'redis';
    case DockerCompose = 'docker-compose';
}
