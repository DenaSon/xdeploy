<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https;

use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;

interface MarzbanHttpsDisabler
{
    public function disable(MarzbanDomain $domain): void;
}
