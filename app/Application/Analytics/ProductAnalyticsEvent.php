<?php

declare(strict_types=1);

namespace App\Application\Analytics;

enum ProductAnalyticsEvent: string
{
    case LandingViewed = 'landing_viewed';
    case AuthenticationCompleted = 'authentication_completed';
    case BuyViewed = 'buy_viewed';
    case ProviderSelected = 'provider_selected';
    case OrderCreated = 'order_created';
    case PaymentStarted = 'payment_started';
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';
    case PaymentCancelled = 'payment_cancelled';
    case ProvisioningStarted = 'provisioning_started';
    case ServerFulfilled = 'server_fulfilled';
    case ServerReady = 'server_ready';
    case ApplicationInstallStarted = 'application_install_started';
    case ApplicationInstallCompleted = 'application_install_completed';
    case ApplicationRunning = 'application_running';
}
