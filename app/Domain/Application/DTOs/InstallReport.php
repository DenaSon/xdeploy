<?php

declare(strict_types=1);

namespace App\Domain\Application\DTOs;

/**
 * @phpstan-type Messages list<InstallMessage>
 */
final readonly class InstallReport
{
    /**
     * @param  Messages  $messages
     */
    public function __construct(
        public array $messages = [],
    ) {}

    public function with(InstallMessage $message): self
    {
        return new self([
            ...$this->messages,
            $message,
        ]);
    }

    public function merge(self $report): self
    {
        return new self([
            ...$this->messages,
            ...$report->messages,
        ]);
    }
}
