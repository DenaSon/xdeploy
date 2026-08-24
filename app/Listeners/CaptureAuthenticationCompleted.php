<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final readonly class CaptureAuthenticationCompleted
{
    public function __construct(
        private ProductAnalytics $analytics,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->analytics->capture(
            ProductAnalyticsEvent::AuthenticationCompleted,
            $event->user->getKey(),
            [
                'guard' => $event->guard,
            ],
        );
    }
}
