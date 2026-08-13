<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https;

interface MarzbanHttpsInterruptedOperationRecovery
{
    public function recover(): void;
}
