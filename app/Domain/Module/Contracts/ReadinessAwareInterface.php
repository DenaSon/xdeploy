<?php
namespace App\Domain\Module\Contracts;
interface ReadinessAwareInterface
{
    public function ensureReady(): void;
}
