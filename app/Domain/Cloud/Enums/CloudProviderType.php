<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudProviderType: string
{
    case Arvan = 'arvan';
    case Liara = 'liara';
}
