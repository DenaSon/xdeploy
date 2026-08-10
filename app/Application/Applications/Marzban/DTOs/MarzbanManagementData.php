<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\DTOs;

use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;

final readonly class MarzbanManagementData
{
    public function __construct(
        public ApplicationInfo $application,
        public MarzbanAdminOverview $setup,
        public MarzbanHttpsInfo $https,
    ) {}

    /**
     * @return array{
     *     application: array{
     *         state: string,
     *         version: string|null,
     *         is_installed: bool,
     *         is_running: bool,
     *         is_not_installed: bool,
     *         is_unknown: bool
     *     },
     *     setup: array{
     *         state: string,
     *         admins: list<array{
     *             username: string
     *         }>
     *     },
     *     https: array{
     *         state: string,
     *         domain: string|null
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'application' => [
                'state' => $this->application->state->value,
                'version' => $this->application->version(),
                'is_installed' => $this->application->isInstalled(),
                'is_running' => $this->application->isRunning(),
                'is_not_installed' => $this->application->isNotInstalled(),
                'is_unknown' => $this->application->isUnknown(),
            ],

            'setup' => $this->setup->toArray(),

            'https' => [
                'state' => $this->https->state->value,

                'domain' => $this->https->domain,
            ],
        ];
    }
}
