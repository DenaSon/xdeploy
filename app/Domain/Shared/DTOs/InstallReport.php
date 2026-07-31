<?php

declare(strict_types=1);

namespace App\Domain\Shared\DTOs;

/**
 * @phpstan-type Messages list<InstallMessage>
 */
final readonly class InstallReport
{
    /**
     * @param  InstallMessage  $messages
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

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * @return list<array{
     *     component: string,
     *     message: string
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (InstallMessage $message): array => [
                'component' => $message->component,
                'message' => $message->message,
            ],
            $this->messages,
        );
    }
}
