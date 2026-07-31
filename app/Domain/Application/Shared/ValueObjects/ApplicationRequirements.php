<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\ValueObjects;

use App\Domain\Platform\Enums\PlatformType;

final readonly class ApplicationRequirements
{
    /**
     * @param  list<string>  $systemPackages
     * @param  list<PlatformType>  $platforms
     */
    public function __construct(
        public array $systemPackages = [],
        public array $platforms = [],
    ) {}
}
