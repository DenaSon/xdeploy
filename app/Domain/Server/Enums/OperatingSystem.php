<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum OperatingSystem: string
{
    case Ubuntu = 'ubuntu';

    case Debian = 'debian';

    case CentOS = 'centos';

    case Rocky = 'rocky';

    case AlmaLinux = 'almalinux';

    case Fedora = 'fedora';

    case Unknown = 'unknown';


    public function label(): string
    {
        return match ($this) {
            self::Ubuntu => 'Ubuntu',
            self::Debian => 'Debian',
            self::CentOS => 'CentOS',
            self::Rocky => 'Rocky Linux',
            self::AlmaLinux => 'AlmaLinux',
            self::Fedora => 'Fedora',
            self::Unknown => 'Unknown',
        };
    }


}


