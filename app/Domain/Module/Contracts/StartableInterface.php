<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

interface StartableInterface
{
    public function start(): void;

    public function stop(): void;

    public function restart(): void;
}
