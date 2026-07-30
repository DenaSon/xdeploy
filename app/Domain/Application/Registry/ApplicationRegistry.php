<?php

declare(strict_types=1);

namespace App\Domain\Application\Registry;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use RuntimeException;

final readonly class ApplicationRegistry implements ApplicationRegistryInterface
{
    /**
     * @param array<int, ApplicationInterface> $applications
     */
    public function __construct(
        private array $applications,
    ) {}

    /**
     * @return array<int, ApplicationInterface>
     */
    public function all(): array
    {
        return $this->applications;
    }

    public function find(ApplicationType $type): ApplicationInterface
    {
        foreach ($this->applications as $application) {
            if ($application->type() === $type) {
                return $application;
            }
        }

        throw new RuntimeException(sprintf(
            'Application [%s] is not registered.',
            $type->value,
        ));
    }
}
