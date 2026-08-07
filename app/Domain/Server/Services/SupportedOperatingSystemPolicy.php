<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\OperatingSystemInfo;

final readonly class SupportedOperatingSystemPolicy
{
    /**
     * Only operating systems explicitly verified by xDeploy
     * are supported. ID_LIKE must not implicitly grant support.
     *
     * @var list<string>
     */
    private const array SUPPORTED_IDS = [
        'ubuntu',
        'debian',
    ];

    public function supports(
        OperatingSystemInfo $operatingSystem,
    ): bool {
        return in_array(
            strtolower($operatingSystem->id),
            self::SUPPORTED_IDS,
            true,
        );
    }

    /**
     * @return list<string>
     */
    public function supportedIds(): array
    {
        return self::SUPPORTED_IDS;
    }
}
