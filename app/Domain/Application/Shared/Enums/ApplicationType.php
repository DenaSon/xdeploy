<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationType: string
{
    case Marzban = 'marzban';
    case N8n = 'n8n';
    case AmneziaWg = 'amneziawg';
    case WordPress = 'wordpress';
}
