<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SettingsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_settings_resolve_with_migrated_defaults(): void
    {
        $general = app(GeneralSettings::class);
        $branding = app(BrandingSettings::class);
        $seo = app(SeoSettings::class);

        self::assertSame('Coreflare', $general->site_name);
        self::assertSame('از سرور تا سرویس، در یک پنل', $branding->tagline);
        self::assertSame('Coreflare | مدیریت VPS و راه‌اندازی سرویس‌ها', $seo->default_title);
        self::assertNotSame('', $seo->default_description);
        self::assertNull($seo->default_og_image);
        self::assertSame('کورفلر', $seo->site_alternate_name);
        self::assertNull($seo->organization_logo);
        self::assertNull($seo->favicon);
        self::assertNull($seo->apple_touch_icon);
        self::assertTrue($seo->index_site);
        self::assertNull($seo->google_site_verification);
        self::assertNull($seo->bing_site_verification);
    }

    public function test_typed_settings_can_be_saved_and_refreshed(): void
    {
        $settings = app(SeoSettings::class);

        $settings->index_site = false;
        $settings->save();
        $settings->refresh();

        self::assertFalse($settings->index_site);
    }
}
