<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Contracts;

interface SSHHostResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(
        string $hostname,
    ): array;
}
