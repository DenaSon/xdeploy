<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint;

use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use InvalidArgumentException;

final readonly class PublicEndpointDriverRegistry
{
    /** @var array<string, PublicEndpointDriverInterface> */
    private array $drivers;

    /** @param list<PublicEndpointDriverInterface> $drivers */
    public function __construct(array $drivers)
    {
        $indexed = [];

        foreach ($drivers as $driver) {
            $key = $driver->type()->value;

            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Duplicate public endpoint driver [{$key}].");
            }

            $indexed[$key] = $driver;
        }

        $this->drivers = $indexed;
    }

    public function find(ApplicationType $type): PublicEndpointDriverInterface
    {
        return $this->drivers[$type->value]
            ?? throw new InvalidArgumentException("Unsupported public endpoint application [{$type->value}].");
    }

    /** @return list<PublicEndpointDriverInterface> */
    public function all(): array
    {
        return array_values($this->drivers);
    }

    public function supports(ApplicationType $type): bool
    {
        return isset($this->drivers[$type->value]);
    }
}
