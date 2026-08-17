<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\ValueObjects;

use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\Shared\Exceptions\InvalidPublicDomainNameException;
use App\Domain\Shared\ValueObjects\PublicDomainName;

final readonly class PublicEndpointDomain
{
    private function __construct(
        public string $value,
    ) {}

    public static function from(string $input): self
    {
        try {
            $domain = PublicDomainName::from($input);
        } catch (InvalidPublicDomainNameException) {
            throw InvalidPublicEndpointDomainException::make();
        }

        return new self($domain->value);
    }
}
