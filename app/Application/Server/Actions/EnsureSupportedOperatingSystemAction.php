<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use App\Infrastructure\Linux\Services\OperatingSystemInspector;

final readonly class EnsureSupportedOperatingSystemAction
{
    public function __construct(
        private OperatingSystemInspector $inspector,
        private SupportedOperatingSystemPolicy $policy,
    ) {}

    public function handle(): OperatingSystemInfo
    {
        $operatingSystem = $this->inspector
            ->inspect();

        if (
            ! $this->policy
                ->supports(
                    $operatingSystem,
                )
        ) {
            throw new UnsupportedOperatingSystemException(
                $operatingSystem,
            );
        }

        return $operatingSystem;
    }
}
