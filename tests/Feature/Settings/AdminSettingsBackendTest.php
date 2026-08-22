<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Application\Settings\GetSystemSettings;
use App\Application\Settings\UpdateBrandingSettings;
use App\Application\Settings\UpdateGeneralSettings;
use App\Application\Settings\UpdateSeoSettings;
use App\Events\SystemSettingsUpdated;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdminSettingsBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_settings_through_application_actions(): void
    {
        Event::fake([SystemSettingsUpdated::class]);

        $admin = User::factory()->create(['is_admin' => true]);

        app(UpdateGeneralSettings::class)->handle($admin, [
            'site_name' => '  Coreflare Cloud  ',
        ]);

        app(UpdateBrandingSettings::class)->handle($admin, [
            'tagline' => '  زیرساخت، ساده و یکپارچه  ',
        ]);

        app(UpdateSeoSettings::class)->handle($admin, [
            'default_title' => '  Coreflare | مدیریت زیرساخت  ',
            'default_description' => '  مدیریت VPS، سرویس‌ها، دامنه و وضعیت زیرساخت در Coreflare.  ',
            'default_og_image' => '  /images/og/coreflare.png  ',
            'index_site' => false,
        ]);

        $snapshot = app(GetSystemSettings::class)->handle();

        self::assertSame('Coreflare Cloud', $snapshot->siteName);
        self::assertSame('زیرساخت، ساده و یکپارچه', $snapshot->tagline);
        self::assertSame('Coreflare | مدیریت زیرساخت', $snapshot->seoDefaultTitle);
        self::assertSame(
            'مدیریت VPS، سرویس‌ها، دامنه و وضعیت زیرساخت در Coreflare.',
            $snapshot->seoDefaultDescription,
        );
        self::assertSame('/images/og/coreflare.png', $snapshot->seoDefaultOgImage);
        self::assertFalse($snapshot->seoIndexSite);

        Event::assertDispatched(
            SystemSettingsUpdated::class,
            fn (SystemSettingsUpdated $event): bool => $event->actorId === $admin->getKey()
                && $event->group === 'general'
                && $event->changedKeys === ['site_name'],
        );

        Event::assertDispatched(
            SystemSettingsUpdated::class,
            fn (SystemSettingsUpdated $event): bool => $event->actorId === $admin->getKey()
                && $event->group === 'branding'
                && $event->changedKeys === ['tagline'],
        );

        Event::assertDispatched(
            SystemSettingsUpdated::class,
            fn (SystemSettingsUpdated $event): bool => $event->actorId === $admin->getKey()
                && $event->group === 'seo'
                && $event->changedKeys === [
                    'default_title',
                    'default_description',
                    'default_og_image',
                    'index_site',
                ],
        );
    }

    public function test_non_admin_cannot_update_system_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->expectException(AuthorizationException::class);

        app(UpdateGeneralSettings::class)->handle($user, [
            'site_name' => 'Unauthorized change',
        ]);
    }

    public function test_invalid_seo_settings_are_rejected_without_persisting(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $before = app(GetSystemSettings::class)->handle();

        try {
            app(UpdateSeoSettings::class)->handle($admin, [
                'default_title' => str_repeat('x', 71),
                'default_description' => 'Valid description',
                'default_og_image' => null,
                'index_site' => true,
            ]);

            self::fail('Expected SEO settings validation to fail.');
        } catch (ValidationException) {
            // Expected: invalid input must not reach persistence.
        }

        $after = app(GetSystemSettings::class)->handle();

        self::assertEquals($before, $after);
    }

    public function test_noop_update_does_not_emit_settings_updated_event(): void
    {
        Event::fake([SystemSettingsUpdated::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $current = app(GetSystemSettings::class)->handle();

        app(UpdateGeneralSettings::class)->handle($admin, [
            'site_name' => $current->siteName,
        ]);

        Event::assertNotDispatched(SystemSettingsUpdated::class);
    }
}
