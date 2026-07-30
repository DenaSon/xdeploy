<?php

declare(strict_types=1);

namespace App\Domain\Server\Distributions;

use App\Domain\Application\Enums\ApplicationType;
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
            self::key(ApplicationType::Docker, LinuxCommand::Exists) => 'command -v docker >/dev/null 2>&1',

            self::key(ApplicationType::Docker, LinuxCommand::Version) => 'docker --version',

            self::key(ApplicationType::Nginx, LinuxCommand::Exists) => 'command -v nginx >/dev/null 2>&1',

            self::key(ApplicationType::Nginx, LinuxCommand::Version) => 'nginx -v 2>&1',

            self::key(ApplicationType::Marzban, LinuxCommand::Exists) => 'test -d /opt/marzban',

            self::key(ApplicationType::Marzban, LinuxCommand::Version) => 'marzban version',

            self::key(ApplicationType::Xray, LinuxCommand::Exists) => 'command -v xray >/dev/null 2>&1',

            self::key(ApplicationType::Xray, LinuxCommand::Version) => 'xray version',

            self::key(ApplicationType::Fail2Ban, LinuxCommand::Exists) => 'command -v fail2ban-client >/dev/null 2>&1',

            self::key(ApplicationType::Fail2Ban, LinuxCommand::Version) => 'fail2ban-client --version',
        ];
    }
}
