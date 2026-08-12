<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites;

use App\Domain\Platform\Caddy\Sites\Exceptions\InvalidCaddySiteException;

final readonly class CaddySiteKey
{
    private function __construct(
        public string $value,
    ) {}

    public static function from(string $input): self
    {
        $key = strtolower(trim($input));

        if (
            preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $key) !== 1
        ) {
            throw InvalidCaddySiteException::key();
        }

        return new self($key);
    }

    public function filename(): string
    {
        return $this->value.'.caddy';
    }
}
