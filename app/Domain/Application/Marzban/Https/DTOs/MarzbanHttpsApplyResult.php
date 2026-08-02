<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

final readonly class MarzbanHttpsApplyResult
{
    public function __construct(
        public string $domain,
        public string $panelUrl,
        public bool $configurationChanged,
    ) {}
}
