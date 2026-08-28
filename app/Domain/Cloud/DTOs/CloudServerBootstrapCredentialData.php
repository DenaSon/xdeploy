<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Server\Enums\AuthenticationType;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class CloudServerBootstrapCredentialData
{
    private string $credential;

    public function __construct(
        public AuthenticationType $authenticationType,
        #[SensitiveParameter]
        string $credential,
    ) {
        if ($credential === '') {
            throw new InvalidArgumentException(
                'Cloud server bootstrap credential cannot be empty.',
            );
        }

        $this->credential = $credential;
    }

    public function credential(): string
    {
        return $this->credential;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'authentication_type' => $this->authenticationType->value,
            'credential' => '[REDACTED]',
        ];
    }
}
