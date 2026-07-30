<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Services\ApplicationService;

final readonly class GetApplicationsAction
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {}

    /**
     * @return list<array{
     *     type: string,
     *     name: string,
     * }>
     */
    public function execute(): array
    {
        return array_map(
            static fn (ApplicationInterface $application): array => [
                'type' => $application->type()->value,
                'name' => $application->name(),
            ],
            $this->applicationService->all(),
        );
    }
}
