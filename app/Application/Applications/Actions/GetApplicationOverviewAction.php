<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Services\ApplicationService;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class GetApplicationOverviewAction
{
    public function __construct(
        private ApplicationService $modules,
    ) {}

    /**
     * @return array<int, array{
     *     type: ApplicationType,
     *     name: string,
     *     info: ApplicationInfo,
     * }>
     */
    public function handle(): array
    {
        return $this->modules->inspectAll();
    }
}
