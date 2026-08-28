<?php

namespace App\Providers;

use App;
use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Billing\Events\PaymentStatusChanged;
use App\Application\Navigation\PublicDocumentationNavigation;
use App\Application\Navigation\PublicFooterNavigation;
use App\Application\Support\Contracts\SupportImageProcessorInterface;
use App\Application\Support\Events\SupportRequestCreated;
use App\Application\User\Events\UserRegistered;
use App\Http\Middleware\EnsureAdminPasskeyVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Infrastructure\Analytics\NullProductAnalytics;
use App\Infrastructure\Analytics\PostHogProductAnalytics;
use App\Infrastructure\Support\LaravelSupportImageProcessor;
use App\Listeners\CaptureAuthenticationCompleted;
use App\Listeners\SendAdminPaymentSucceededNotification;
use App\Listeners\SendAdminSupportRequestCreatedNotification;
use App\Listeners\SendAdminUserRegisteredNotification;
use App\Listeners\SendPaymentStatusNotification;
use App\Livewire\Applications\WordPress\ManagementPanel as WordPressManagementPanel;
use App\Models\ApplicationOperation;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\Order;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use App\Observers\ApplicationOperationAnalyticsObserver;
use App\Observers\OrderAnalyticsObserver;
use App\Observers\PaymentAnalyticsObserver;
use App\Observers\PaymentNotificationObserver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passkeys::ignoreRoutes();

        $this->app->bind(
            SupportImageProcessorInterface::class,
            LaravelSupportImageProcessor::class,
        );

        $this->app->singleton(
            ProductAnalytics::class,
            static fn ($app): ProductAnalytics => (bool) config(
                'services.posthog.enabled',
                false,
            )
                ? $app->make(PostHogProductAnalytics::class)
                : $app->make(NullProductAnalytics::class),
        );
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        App::setLocale('fa');

        Event::listen(
            Login::class,
            CaptureAuthenticationCompleted::class,
        );

        Event::listen(
            UserRegistered::class,
            SendAdminUserRegisteredNotification::class,
        );

        Event::listen(
            SupportRequestCreated::class,
            SendAdminSupportRequestCreatedNotification::class,
        );

        Event::listen(
            PaymentStatusChanged::class,
            SendPaymentStatusNotification::class,
        );

        Event::listen(
            PaymentStatusChanged::class,
            SendAdminPaymentSucceededNotification::class,
        );

        Order::observe(OrderAnalyticsObserver::class);
        Payment::observe(PaymentAnalyticsObserver::class);
        Payment::observe(PaymentNotificationObserver::class);
        ApplicationOperation::observe(
            ApplicationOperationAnalyticsObserver::class,
        );

        $this->registerLogViewerAuthorization();
        $this->registerPublicDocumentationNavigationCacheInvalidation();
        $this->registerPublicFooterNavigationCacheInvalidation();

        Livewire::component(
            'applications.wordpress.management-panel',
            WordPressManagementPanel::class,
        );

        Livewire::addPersistentMiddleware([
            EnsureUserIsAdmin::class,
            EnsureAdminPasskeyVerified::class,
        ]);

        Route::bind(
            'server',
            static function (string $value): Server {
                $user = Auth::user();

                if (! $user instanceof User) {
                    throw new AuthenticationException;
                }

                return $user->servers()
                    ->whereKey($value)
                    ->firstOrFail();
            },
        );
    }

    private function registerLogViewerAuthorization(): void
    {
        Gate::define(
            'viewLogViewer',
            static fn (User $user): bool => $user->isAdmin(),
        );

        Gate::define(
            'downloadLogFile',
            static fn (User $user): bool => $user->isAdmin(),
        );

        Gate::define(
            'downloadLogFolder',
            static fn (User $user): bool => $user->isAdmin(),
        );

        // Production log deletion stays disabled from the web UI. Rotation and
        // retention should remain an explicit infrastructure responsibility.
        Gate::define(
            'deleteLogFile',
            static fn (): bool => false,
        );

        Gate::define(
            'deleteLogFolder',
            static fn (): bool => false,
        );
    }

    private function registerPublicDocumentationNavigationCacheInvalidation(): void
    {
        DocumentationCategory::saved(
            static function (): void {
                app(PublicDocumentationNavigation::class)->forget();
            },
        );

        DocumentationCategory::deleted(
            static function (): void {
                app(PublicDocumentationNavigation::class)->forget();
            },
        );

        DocumentationArticle::saved(
            static function (): void {
                app(PublicDocumentationNavigation::class)->forget();
            },
        );

        DocumentationArticle::deleted(
            static function (): void {
                app(PublicDocumentationNavigation::class)->forget();
            },
        );
    }

    private function registerPublicFooterNavigationCacheInvalidation(): void
    {
        Page::saved(
            static function (): void {
                app(PublicFooterNavigation::class)->forget();
            },
        );

        Page::deleted(
            static function (): void {
                app(PublicFooterNavigation::class)->forget();
            },
        );
    }
}
