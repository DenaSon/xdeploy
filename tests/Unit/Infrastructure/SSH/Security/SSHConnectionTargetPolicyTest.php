<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SSH\Security;

use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionTargetNotAllowedException;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SSHConnectionTargetPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'xdeploy.ssh.allow_private_targets',
            false,
        );
    }

    public function test_public_ipv4_is_allowed(): void
    {
        $policy = $this->policy();

        self::assertSame(
            '8.8.8.8',
            $policy->resolve('8.8.8.8'),
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

    #[DataProvider('nonPublicIpProvider')]
    public function test_non_public_ip_is_rejected_by_default(
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

    #[DataProvider('developmentIpProvider')]
    public function test_private_and_loopback_ips_can_be_allowed_in_development(
        string $address,
    ): void {
        $this->allowPrivateTargets();

        $policy = $this->policy();

        self::assertSame(
            $address,
            $policy->resolve(
                $address,
            ),
        );
    }

    #[DataProvider('alwaysBlockedIpProvider')]
    public function test_unsafe_addresses_remain_blocked_even_when_private_targets_are_enabled(
        string $address,
    ): void {
        $this->allowPrivateTargets();

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

    public function test_hostname_resolving_to_private_ip_is_rejected_by_default(): void
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

    public function test_hostname_resolving_to_private_ip_can_be_allowed_in_development(): void
    {
        $this->allowPrivateTargets();

        $policy = $this->policy([
            '192.168.1.20',
        ]);

        self::assertSame(
            '192.168.1.20',
            $policy->resolve(
                'vps.example.com',
            ),
        );
    }

    public function test_hostname_resolving_to_loopback_is_rejected_by_default(): void
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

    public function test_hostname_resolving_to_loopback_can_be_allowed_in_development(): void
    {
        $this->allowPrivateTargets();

        $policy = $this->policy([
            '127.0.0.1',
        ]);

        self::assertSame(
            '127.0.0.1',
            $policy->resolve(
                'vps.example.com',
            ),
        );
    }

    public function test_hostname_with_mixed_public_and_private_addresses_is_rejected_by_default(): void
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

    public function test_hostname_with_mixed_allowed_addresses_can_be_used_in_development(): void
    {
        $this->allowPrivateTargets();

        $policy = $this->policy([
            '8.8.8.8',
            '10.0.0.10',
        ]);

        self::assertSame(
            '8.8.8.8',
            $policy->resolve(
                'vps.example.com',
            ),
        );
    }

    public function test_hostname_with_link_local_address_remains_rejected_in_development(): void
    {
        $this->allowPrivateTargets();

        $policy = $this->policy([
            '8.8.8.8',
            '169.254.169.254',
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
     * All non-public addresses must be rejected
     * while the development exception is disabled.
     *
     * @return array<string, array{string}>
     */
    public static function nonPublicIpProvider(): array
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
     * Explicit ranges permitted only during
     * local/testing development.
     *
     * @return array<string, array{string}>
     */
    public static function developmentIpProvider(): array
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

            'ipv6 loopback' => [
                '::1',
            ],

            'ipv6 unique local' => [
                'fd00::1',
            ],
        ];
    }

    /**
     * These ranges must never be permitted by
     * the private-development exception.
     *
     * @return array<string, array{string}>
     */
    public static function alwaysBlockedIpProvider(): array
    {
        return [
            'ipv4 link local' => [
                '169.254.169.254',
            ],

            'ipv4 unspecified' => [
                '0.0.0.0',
            ],

            'ipv6 link local' => [
                'fe80::1',
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

    private function allowPrivateTargets(): void
    {
        config()->set(
            'xdeploy.ssh.allow_private_targets',
            true,
        );
    }

    /**
     * @param list<string> $addresses
     */
    private function policy(
        array $addresses = [],
    ): SSHConnectionTargetPolicy {
        $resolver = new class($addresses) implements SSHHostResolverInterface
        {
            /**
             * @param list<string> $addresses
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
