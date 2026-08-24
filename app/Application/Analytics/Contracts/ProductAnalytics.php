<?php

declare(strict_types=1);

namespace App\Application\Analytics\Contracts;

use App\Application\Analytics\ProductAnalyticsEvent;

interface ProductAnalytics
{
    /**
     * Capture a product event using only explicitly selected, non-sensitive
     * properties. Implementations must never make the product workflow fail.
     *
     * @param  array<string, mixed>  $properties
     */
    public function capture(
        ProductAnalyticsEvent $event,
        int|string $userId,
        array $properties = [],
    ): void;
}
