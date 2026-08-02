<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanCaddyfileFactory;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanComposeOverrideFactory;
use PHPUnit\Framework\TestCase;

final class MarzbanHttpsConfigurationFactoryTest extends TestCase
{
    public function test_it_builds_a_managed_caddyfile_for_the_validated_domain(): void
    {
        $caddyfile = (new MarzbanCaddyfileFactory)->make(
            MarzbanDomain::from('panel.example.com'),
        );

        self::assertStringContainsString(
            '# xDeploy: marzban-https',
            $caddyfile,
        );
        self::assertStringContainsString(
            'panel.example.com {',
            $caddyfile,
        );
        self::assertStringContainsString(
            'reverse_proxy unix//var/lib/marzban/marzban.socket',
            $caddyfile,
        );
    }

    public function test_it_builds_an_isolated_compose_override_for_caddy(): void
    {
        $compose = (new MarzbanComposeOverrideFactory)->make();

        self::assertStringContainsString('caddy:2-alpine', $compose);
        self::assertStringContainsString('"80:80"', $compose);
        self::assertStringContainsString('"443:443"', $compose);
        self::assertStringContainsString(
            './Caddyfile:/etc/caddy/Caddyfile:ro',
            $compose,
        );
        self::assertStringNotContainsString(
            'gozargah/marzban',
            $compose,
        );
    }
}
