<?php

namespace App\Providers;

use App;
use App\Application\Navigation\PublicDocumentationNavigation;
use App\Application\Navigation\PublicFooterNavigation;
use App\Http\Middleware\EnsureAdminPasskeyVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\Page;
use App\Models\Server;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
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
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        App::setLocale('fa');

        $this->registerPublicDocumentationNavigationCacheInvalidation();
        $this->registerPublicFooterNavigationCacheInvalidation();

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
