<?php

declare(strict_types=1);

namespace App\Domain\Module\Enums;

enum ModuleType: string
{
    case Docker = 'docker';
    case Nginx = 'nginx';
    case Marzban = 'marzban';
    case Xray = 'xray';
    case Fail2Ban = 'fail2ban';
}
