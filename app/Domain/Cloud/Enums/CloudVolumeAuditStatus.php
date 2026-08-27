<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudVolumeAuditStatus: string
{
    case Linked = 'linked';
    case Detached = 'detached';
    case Orphan = 'orphan';
    case Ambiguous = 'ambiguous';
}
