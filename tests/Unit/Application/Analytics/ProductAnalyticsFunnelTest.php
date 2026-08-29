<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Analytics;

use App\Application\Analytics\ProductAnalyticsFunnel;
use PHPUnit\Framework\TestCase;

final class ProductAnalyticsFunnelTest extends TestCase
{
    public function test_purchase_to_activation_funnel_has_stable_canonical_order(): void
    {
        $this->assertSame(
            [
                'landing_viewed',
                'authentication_completed',
                'buy_viewed',
                'order_created',
                'payment_started',
                'payment_succeeded',
                'provisioning_started',
                'server_fulfilled',
                'server_ready',
                'application_install_started',
                'application_running',
            ],
            array_map(
                static fn ($event): string => $event->value,
                ProductAnalyticsFunnel::purchaseToActivation(),
            ),
        );
    }
}
