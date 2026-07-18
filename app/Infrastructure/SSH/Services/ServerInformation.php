<?php

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

readonly class ServerInformation
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function hostname(): string
    {
        return trim(
            $this->ssh->execute('hostname')
        );
    }

    public function whoami(): string
    {
        return trim(
            $this->ssh->execute('whoami')
        );
    }

    public function uptime(): string
    {
        return trim(
            $this->ssh->execute('uptime')
        );
    }

    public function os(): string
    {
        return trim(
            $this->ssh->execute('cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'')
        );
    }

}
