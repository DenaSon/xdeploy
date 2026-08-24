<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;

final class NullProductAnalytics implements ProductAnalytics
{
    public function capture(
        ProductAnalyticsEvent $event,
        int|string $userId,
        array $properties = [],
    ): void {}
}
