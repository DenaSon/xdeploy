<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudReportMetric: string
{
    case CpuUsage = 'cpu_usage';

    case RamUsage = 'ram_usage';

    case NetworkIncoming = 'network_incoming';

    case NetworkOutgoing = 'network_outgoing';

    case DiskRead = 'disk_read';

    case DiskWrite = 'disk_write';
}
