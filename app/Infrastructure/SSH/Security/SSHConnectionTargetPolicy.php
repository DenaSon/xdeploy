<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Security;

use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionTargetNotAllowedException;
use Symfony\Component\HttpFoundation\IpUtils;

final readonly class SSHConnectionTargetPolicy
{
    /**
     * Private ranges allowed only for local development/testing.
     *
     * Link-local, multicast, documentation and other reserved
     * ranges are intentionally not included.
     *
     * @var list<string>
     */
    private const array DEVELOPMENT_NETWORKS = [
        // IPv4 loopback
        '127.0.0.0/8',

        // RFC1918
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',

        // IPv6 loopback
        '::1/128',

        // IPv6 unique-local
        'fc00::/7',
    ];

    public function __construct(
        private SSHHostResolverInterface $resolver,
    ) {}

    public function resolve(
        string $host,
    ): string {
        $host = $this->normalizeHost(
            $host,
        );

        /*
         * Literal IP.
         */
        if (
            filter_var(
                $host,
                FILTER_VALIDATE_IP,
            ) !== false
        ) {
            $this->ensureAllowedAddress(
                $host,
            );

            return $host;
        }

        /*
         * Public SSH targets must use a fully-qualified hostname.
         */
        if (
            ! str_contains(
                $host,
                '.',
            )
            || filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME,
            ) === false
        ) {
            throw new SSHConnectionTargetNotAllowedException(
                'SSH target hostname is invalid.',
            );
        }

        $addresses = $this->resolver->resolve(
            $host,
        );

        if ($addresses === []) {
            throw new SSHConnectionTargetNotAllowedException(
                'SSH target hostname could not be resolved.',
            );
        }

        /*
         * Every resolved address must be allowed.
         */
        foreach ($addresses as $address) {
            $this->ensureAllowedAddress(
                $address,
            );
        }

        /*
         * Connect directly to the validated address to reduce
         * DNS-rebinding / TOCTOU risk.
         */
        return $addresses[0];
    }

    private function normalizeHost(
        string $host,
    ): string {
        $host = strtolower(
            trim(
                $host,
            ),
        );

        $host = rtrim(
            $host,
            '.',
        );

        if ($host === '') {
            throw new SSHConnectionTargetNotAllowedException(
                'SSH target host cannot be empty.',
            );
        }

        return $host;
    }

    private function ensureAllowedAddress(
        string $address,
    ): void {
        /*
         * Production-safe path.
         */
        if (
            filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_GLOBAL_RANGE,
            ) !== false
        ) {
            return;
        }

        /*
         * Explicit development-only exception.
         */
        if (
            $this->privateTargetsAreAllowed()
            && IpUtils::checkIp(
                $address,
                self::DEVELOPMENT_NETWORKS,
            )
        ) {
            return;
        }

        throw new SSHConnectionTargetNotAllowedException(
            'SSH connections to this network address are not allowed.',
        );
    }

    private function privateTargetsAreAllowed(): bool
    {
        return app()->environment([
            'local',
            'testing',
        ]) && (bool) config(
            'xdeploy.ssh.allow_private_targets',
            false,
        );
    }
}
