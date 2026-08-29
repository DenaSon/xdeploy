<?php

declare(strict_types=1);

namespace App\Application\Analytics;

final class ProductAnalyticsFunnel
{
    /**
     * Canonical business funnel used for product reporting.
     *
     * Keep this list ordered and additive. Payment failure/cancellation are
     * outcome events, not success-path funnel steps.
     *
     * @return list<ProductAnalyticsEvent>
     */
    public static function purchaseToActivation(): array
    {
        return [
            ProductAnalyticsEvent::LandingViewed,
            ProductAnalyticsEvent::AuthenticationCompleted,
            ProductAnalyticsEvent::BuyViewed,
            ProductAnalyticsEvent::OrderCreated,
            ProductAnalyticsEvent::PaymentStarted,
            ProductAnalyticsEvent::PaymentSucceeded,
            ProductAnalyticsEvent::ProvisioningStarted,
            ProductAnalyticsEvent::ServerFulfilled,
            ProductAnalyticsEvent::ServerReady,
            ProductAnalyticsEvent::ApplicationInstallStarted,
            ProductAnalyticsEvent::ApplicationRunning,
        ];
    }
}
