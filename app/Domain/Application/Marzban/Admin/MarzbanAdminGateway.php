<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Admin;

use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;

interface MarzbanAdminGateway
{
    public function inspect(
        ?string $username = null,
    ): MarzbanSetupState;

    public function create(
        string $username,
        string $password,
    ): void;
}
