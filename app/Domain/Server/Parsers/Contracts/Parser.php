<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers\Contracts;

interface Parser
{
    public function parse(string $output): mixed;
}
