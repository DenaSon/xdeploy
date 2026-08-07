<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Security;

use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionTargetNotAllowedException;

final readonly class SSHConnectionTargetPolicy
{
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
            $this->ensureGloballyRoutable(
                $host,
            );

            return $host;
        }

        /*
         * Public SSH targets must use either a public IP
         * address or a fully-qualified hostname.
         *
         * Reject single-label names such as:
         * localhost, database, redis, metadata, etc.
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
         * Every resolved address must be safe.
         *
         * If even one DNS result points to a private,
         * loopback or otherwise non-public address,
         * reject the hostname completely.
         */
        foreach ($addresses as $address) {
            $this->ensureGloballyRoutable(
                $address,
            );
        }

        /*
         * Return the validated IP instead of the hostname.
         *
         * SSHConnection connects directly to this address,
         * avoiding another DNS lookup and reducing
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

    private function ensureGloballyRoutable(
        string $address,
    ): void {
        if (
            filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_GLOBAL_RANGE,
            ) !== false
        ) {
            return;
        }

        throw new SSHConnectionTargetNotAllowedException(
            'SSH connections to non-public network addresses are not allowed.',
        );
    }
}
