<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Infrastructure\Platform\Caddy\Configuration\CaddySiteConfigurationFactory;
use PHPUnit\Framework\TestCase;

final class CaddySiteConfigurationFactoryTest extends TestCase
{
    public function test_it_builds_a_managed_reverse_proxy_site(): void
    {
        $site = CaddySite::reverseProxy(
            key: CaddySiteKey::from('marzban'),
            domain: 'panel.example.com',
            upstream: 'unix//var/lib/marzban/marzban.socket',
        );

        $configuration = (
            new CaddySiteConfigurationFactory
        )->make($site);

        self::assertSame(
            <<<'CADDY'
# xDeploy: caddy-site:marzban
panel.example.com {
    reverse_proxy unix//var/lib/marzban/marzban.socket
}

CADDY,
            $configuration,
        );
    }
}
