<?php

declare(strict_types=1);

namespace App\Domain\Authentication\ValueObjects;

use InvalidArgumentException;

final readonly class OtpCode
{
    private const int LENGTH = 5;

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
        return new self(
            str_pad(
                (string) random_int(0, 99_999),
                self::LENGTH,
                '0',
                STR_PAD_LEFT,
            ),
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
