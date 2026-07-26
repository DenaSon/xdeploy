<?php

declare(strict_types=1);

namespace App\Domain\Authentication\ValueObjects;

use InvalidArgumentException;

final readonly class OtpCode
{
    public function __construct(
        public string $value,
    ) {
        $this->validate();
    }

    public static function from(
        string $value,
    ): self {
        return new self($value);
    }

    public static function generate(
        int $length = 6,
    ): self {
        if ($length < 4 || $length > 8) {
            throw new InvalidArgumentException(
                'OTP length must be between 4 and 8 digits.',
            );
        }

        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return new self(
            (string) random_int($min, $max),
        );
    }

    private function validate(): void
    {
        if (! preg_match('/^\d{4,8}$/', $this->value)) {
            throw new InvalidArgumentException(
                'Invalid OTP code.',
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
