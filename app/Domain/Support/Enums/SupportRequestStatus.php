<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum SupportRequestStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';
}
