<?php

declare(strict_types=1);

namespace App\Domain\Authentication\ValueObjects;

use InvalidArgumentException;

final readonly class OtpCode
{
    private const int LENGTH = 4;

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

    public static function generate(): self
    {
        $min = 10 ** (self::LENGTH - 1);
        $max = (10 ** self::LENGTH) - 1;

        return new self(
            (string) random_int($min, $max),
        );
    }

    private function validate(): void
    {
        if (! preg_match(
            '/^\d{'.self::LENGTH.'}$/',
            $this->value,
        )) {
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
