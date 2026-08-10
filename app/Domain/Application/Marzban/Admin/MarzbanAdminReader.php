<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Admin;

use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;

interface MarzbanAdminReader
{
    public function overview(): MarzbanAdminOverview;
}
