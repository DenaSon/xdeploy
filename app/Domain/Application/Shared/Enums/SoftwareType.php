<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

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
    case N8n = 'n8n';
    case AmneziaWg = 'amneziawg';

    public function label(): string
    {
        return match ($this) {
            self::Docker => 'Docker',
            self::DockerCompose => 'Docker Compose',
            self::Nginx => 'Nginx',
            self::PHP => 'PHP',
            self::Composer => 'Composer',
            self::Git => 'Git',
            self::Redis => 'Redis',
            self::Marzban => 'Marzban',
            self::Xray => 'Xray',
            self::N8n => 'n8n',
            self::AmneziaWg => 'AmneziaWG',
        };
    }
}
