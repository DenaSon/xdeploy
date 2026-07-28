<?php

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\ServerOverviewData;
use App\Domain\Server\DTOs\ServiceStatusData;

readonly class ServerService
{
    public function __construct(
        private ServerInspector $inspector,
    ) {}

    /**
     * Get a complete server overview.
     */
    public function overview(): ServerOverviewData
    {
        return new ServerOverviewData(
            hostname: $this->inspector->hostname(),
            operatingSystem: $this->inspector->operatingSystem(),
            kernel: $this->inspector->kernel(),
            uptime: $this->inspector->uptime(),
            user: $this->inspector->whoami(),
            privateIp: $this->inspector->privateIp(),
            cpu: $this->inspector->cpu(),
            memory: $this->inspector->memory(),
            disk: $this->inspector->disk(),
            loadAverage: $this->inspector->loadAverage(),

            // جدید
            services: $this->services(),
        );
    }

    /**
     * Get server services status.
     *
     * @return ServiceStatusData[]
     */
    public function services(): array
    {
        $services = [
            'SSH' => 'ssh',
            'Docker' => 'docker',
            'Nginx' => 'nginx',
            'Marzban' => 'marzban',
            'Xray' => 'xray',
            'Fail2Ban' => 'fail2ban',
            'UFW' => 'ufw',
            'Redis' => 'redis',
            'MySQL' => 'mysql',
        ];

        $result = [];

        foreach ($services as $name => $service) {
            $result[] = new ServiceStatusData(
                name: $name,
                status: $this->inspector->serviceStatus($service),
            );
        }

        return $result;
    }
}
