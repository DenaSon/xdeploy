<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum SupportMessageAuthorRole: string
{
    case User = 'user';
    case Admin = 'admin';
}
