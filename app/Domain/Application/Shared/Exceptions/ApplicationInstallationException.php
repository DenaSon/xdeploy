<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Exceptions;

use Throwable;

final class ApplicationInstallationException extends ApplicationException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $failureCode = null,
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
        );
    }

    /** @return array<string, string> */
    public function context(): array
    {
        if ($this->failureCode === null) {
            return [];
        }

        return [
            'failure_code' => $this->failureCode,
        ];
    }
}
