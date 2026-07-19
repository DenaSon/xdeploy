<?php

declare(strict_types=1);

namespace App\Domain\Server\Distributions;

use App\Domain\Module\Enums\ModuleType;
use App\Domain\Server\Abstracts\AbstractDistribution;
use App\Domain\Server\Enums\DistributionType;
use App\Domain\Server\Enums\LinuxCommand;

final class Ubuntu extends AbstractDistribution
{
    public function type(): DistributionType
    {
        return DistributionType::Ubuntu;
    }

    /**
     * @return array<string, non-empty-string>
     */
    protected function commands(): array
    {
        return [
            self::key(ModuleType::Docker, LinuxCommand::Exists) => 'command -v docker >/dev/null 2>&1',

            self::key(ModuleType::Docker, LinuxCommand::Version) => 'docker --version',

            self::key(ModuleType::Nginx, LinuxCommand::Exists) => 'command -v nginx >/dev/null 2>&1',

            self::key(ModuleType::Nginx, LinuxCommand::Version) => 'nginx -v 2>&1',

            self::key(ModuleType::Marzban, LinuxCommand::Exists) => 'test -d /opt/marzban',

            self::key(ModuleType::Marzban, LinuxCommand::Version) => 'marzban version',

            self::key(ModuleType::Xray, LinuxCommand::Exists) => 'command -v xray >/dev/null 2>&1',

            self::key(ModuleType::Xray, LinuxCommand::Version) => 'xray version',

            self::key(ModuleType::Fail2Ban, LinuxCommand::Exists) => 'command -v fail2ban-client >/dev/null 2>&1',

            self::key(ModuleType::Fail2Ban, LinuxCommand::Version) => 'fail2ban-client --version',
        ];
    }
}
