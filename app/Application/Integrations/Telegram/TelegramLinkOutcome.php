<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

final readonly class TelegramLinkOutcome
{
    public function __construct(
        public TelegramLinkStatus $status,
        public ?string $chatId = null,
    ) {}
}
