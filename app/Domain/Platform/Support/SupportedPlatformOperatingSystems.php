<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Server\DTOs\OperatingSystemInfo;

final class SupportedPlatformOperatingSystems
{
    /**
     * Operating systems offered by xDeploy that support the Docker stack.
     *
     * @var array<string, list<string>>
     */
    private const array DOCKER_STACK = [
        'debian' => [
            '12',
        ],
        'ubuntu' => [
            '22.04',
            '24.04',
            '26.04',
        ],
    ];

    public static function supportsDockerStack(
        OperatingSystemInfo $operatingSystem,
    ): bool {
        return in_array(
            $operatingSystem->versionId,
            self::DOCKER_STACK[$operatingSystem->id] ?? [],
            true,
        );
    }

    public static function dockerStackDisplayList(): string
    {
        return 'Debian 12, Ubuntu 22.04, Ubuntu 24.04, Ubuntu 26.04';
    }
}
