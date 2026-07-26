<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public function __construct(
        public string $value,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (! preg_match('/^09\d{9}$/', $this->value)) {
            throw new InvalidArgumentException(
                'Invalid phone number.',
            );
        }
    }

    public static function from(string $phone): self
    {
        return new self($phone);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
