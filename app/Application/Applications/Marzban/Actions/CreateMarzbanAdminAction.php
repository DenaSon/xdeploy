<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Application\Applications\Marzban\DTOs\CreateMarzbanAdminData;
use App\Domain\Application\Marzban\Admin\MarzbanAdminService;

final readonly class CreateMarzbanAdminAction
{
    public function __construct(
        private MarzbanAdminService $adminService,
    ) {}

    public function execute(
        CreateMarzbanAdminData $data,
    ): void {
        $this->adminService->create(
            username: $data->username,
            password: $data->password,
        );
    }
}
