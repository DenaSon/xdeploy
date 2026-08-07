<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SSH\Security;

use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionTargetNotAllowedException;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SSHConnectionTargetPolicyTest extends TestCase
{
    public function test_public_ipv4_is_allowed(): void
    {
        $policy = $this->policy();

        self::assertSame(
            '8.8.8.8',
            $policy->resolve(
                '8.8.8.8',
            ),
        );
    }

    public function test_public_ipv6_is_allowed(): void
    {
        $policy = $this->policy();

        self::assertSame(
            '2001:4860:4860::8888',
            $policy->resolve(
                '2001:4860:4860::8888',
            ),
        );
    }

    #[DataProvider('blockedIpProvider')]
    public function test_non_public_ip_is_rejected(
        string $address,
    ): void {
        $policy = $this->policy();

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            $address,
        );
    }

    #[DataProvider('invalidHostnameProvider')]
    public function test_invalid_hostname_is_rejected(
        string $hostname,
    ): void {
        $policy = $this->policy();

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            $hostname,
        );
    }

    public function test_public_hostname_resolves_to_public_ip(): void
    {
        $policy = $this->policy([
            '8.8.8.8',
        ]);

        self::assertSame(
            '8.8.8.8',
            $policy->resolve(
                'vps.example.com',
            ),
        );
    }

    public function test_hostname_resolving_to_private_ip_is_rejected(): void
    {
        $policy = $this->policy([
            '192.168.1.20',
        ]);

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            'vps.example.com',
        );
    }

    public function test_hostname_resolving_to_loopback_ip_is_rejected(): void
    {
        $policy = $this->policy([
            '127.0.0.1',
        ]);

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            'vps.example.com',
        );
    }

    public function test_hostname_with_mixed_public_and_private_addresses_is_rejected(): void
    {
        $policy = $this->policy([
            '8.8.8.8',
            '10.0.0.10',
        ]);

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            'vps.example.com',
        );
    }

    public function test_unresolvable_hostname_is_rejected(): void
    {
        $policy = $this->policy([]);

        $this->expectException(
            SSHConnectionTargetNotAllowedException::class,
        );

        $policy->resolve(
            'vps.example.com',
        );
    }

    public function test_hostname_is_normalized_before_resolution(): void
    {
        $resolver = new class implements SSHHostResolverInterface
        {
            public ?string $resolvedHostname = null;

            public function resolve(
                string $hostname,
            ): array {
                $this->resolvedHostname = $hostname;

                return [
                    '8.8.8.8',
                ];
            }
        };

        $policy = new SSHConnectionTargetPolicy(
            $resolver,
        );

        self::assertSame(
            '8.8.8.8',
            $policy->resolve(
                '  VPS.EXAMPLE.COM.  ',
            ),
        );

        self::assertSame(
            'vps.example.com',
            $resolver->resolvedHostname,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedIpProvider(): array
    {
        return [
            'ipv4 loopback' => [
                '127.0.0.1',
            ],

            'ipv4 private 10' => [
                '10.0.0.1',
            ],

            'ipv4 private 172' => [
                '172.16.0.1',
            ],

            'ipv4 private 192' => [
                '192.168.1.1',
            ],

            'ipv4 link local' => [
                '169.254.169.254',
            ],

            'ipv4 unspecified' => [
                '0.0.0.0',
            ],

            'ipv6 loopback' => [
                '::1',
            ],

            'ipv6 link local' => [
                'fe80::1',
            ],

            'ipv6 unique local' => [
                'fd00::1',
            ],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHostnameProvider(): array
    {
        return [
            'localhost' => [
                'localhost',
            ],

            'single label hostname' => [
                'database',
            ],

            'hostname with spaces' => [
                'invalid host.example.com',
            ],

            'empty hostname' => [
                '',
            ],

            'whitespace hostname' => [
                '   ',
            ],
        ];
    }

    /**
     * @param  list<string>  $addresses
     */
    private function policy(
        array $addresses = [],
    ): SSHConnectionTargetPolicy {
        $resolver = new class($addresses) implements SSHHostResolverInterface
        {
            /**
             * @param  list<string>  $addresses
             */
            public function __construct(
                private readonly array $addresses,
            ) {}

            public function resolve(
                string $hostname,
            ): array {
                return $this->addresses;
            }
        };

        return new SSHConnectionTargetPolicy(
            $resolver,
        );
    }
}
