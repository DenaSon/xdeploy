<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;

final readonly class MarzbanHttpsInfo
{
    public function __construct(
        public MarzbanHttpsState $state,
        public ?string $domain = null,
    ) {}

    public static function unknown(): self
    {
        return new self(
            state: MarzbanHttpsState::Unknown,
        );
    }
}
