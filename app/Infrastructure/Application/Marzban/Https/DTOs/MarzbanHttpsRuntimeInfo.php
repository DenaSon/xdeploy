<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https\DTOs;

final readonly class MarzbanHttpsRuntimeInfo
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $uds,
        public ?string $sslCertificateFile,
        public ?string $sslKeyFile,
        public ?string $subscriptionUrl,
    ) {}

    public function usesManagedReverseProxyRuntime(): bool
    {
        return $this->host === '127.0.0.1'
            && $this->port === 8000
            && $this->uds === null
            && $this->sslCertificateFile === null
            && $this->sslKeyFile === null;
    }
}
