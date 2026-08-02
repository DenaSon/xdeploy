<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

final readonly class MarzbanHttpsRecoveryResult
{
    public function __construct(
        public bool $configurationRestored,
        public bool $servicesRecovered,
    ) {}

    public function successful(): bool
    {
        return $this->configurationRestored
            && $this->servicesRecovered;
    }
}
