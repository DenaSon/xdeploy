<?php

namespace App\Shared\Contracts;

/**
 * @return array<string, mixed>
 */
interface ArrayableData
{
    public function toArray(): array;
}
