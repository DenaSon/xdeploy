<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;

final readonly class SystemSSHHostResolver implements SSHHostResolverInterface
{
    public function resolve(
        string $hostname,
    ): array {
        $records = @dns_get_record(
            $hostname,
            DNS_A | DNS_AAAA,
        );

        if (! is_array($records)) {
            return [];
        }

        $ipv4 = [];
        $ipv6 = [];

        foreach ($records as $record) {
            $address = $record['ip']
                ?? null;

            if (
                is_string($address)
                && filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4,
                ) !== false
            ) {
                $ipv4[] = $address;
            }

            $address = $record['ipv6']
                ?? null;

            if (
                is_string($address)
                && filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6,
                ) !== false
            ) {
                $ipv6[] = $address;
            }
        }

        return array_values(
            array_unique([
                ...$ipv4,
                ...$ipv6,
            ]),
        );
    }
}
