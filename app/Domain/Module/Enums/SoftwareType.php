<?php

declare(strict_types=1);

namespace App\Domain\Module\Enums;

enum SoftwareType: string
{
    case Docker = 'docker';
    case DockerCompose = 'docker-compose';

    case Nginx = 'nginx';
    case PHP = 'php';

    case Composer = 'composer';
    case Git = 'git';
    case Redis = 'redis';

    case Marzban = 'marzban';
    case Xray = 'xray';

    public function label(): string
    {
        return match ($this) {
            self::Docker => 'Docker',
            self::DockerCompose => 'Docker Compose',
            self::PHP => 'PHP',
            self::Composer => 'Composer',
            self::Git => 'Git',
            self::Redis => 'Redis',
            self::Nginx => 'Nginx',
            self::Marzban => 'Marzban',
            self::Xray => 'Xray',
        };
    }
}
