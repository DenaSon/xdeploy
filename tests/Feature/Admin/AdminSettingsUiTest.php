<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Settings\GetSystemSettings;
use App\Livewire\Admin\Settings\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminSettingsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_system_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    public function test_non_admin_cannot_open_system_settings_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_save_each_settings_group_from_livewire_screen(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Index::class)
            ->set('siteName', '  Coreflare Platform  ')
            ->call('saveGeneral')
            ->assertHasNoErrors()
            ->assertSet('siteName', 'Coreflare Platform')
            ->assertSet('savedSection', 'general')
            ->set('tagline', '  از سرور تا سرویس  ')
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertSet('tagline', 'از سرور تا سرویس')
            ->assertSet('savedSection', 'branding')
            ->set('seoDefaultTitle', '  Coreflare | مدیریت زیرساخت  ')
            ->set('seoDefaultDescription', '  مدیریت سرور و سرویس‌ها از یک محیط واحد.  ')
            ->set('seoDefaultOgImage', '  /images/og/coreflare.png  ')
            ->set('seoIndexSite', false)
            ->set('seoGoogleSiteVerification', '  google-token  ')
            ->set('seoBingSiteVerification', '  bing-token  ')
            ->call('saveSeo')
            ->assertHasNoErrors()
            ->assertSet('seoDefaultTitle', 'Coreflare | مدیریت زیرساخت')
            ->assertSet('seoDefaultDescription', 'مدیریت سرور و سرویس‌ها از یک محیط واحد.')
            ->assertSet('seoDefaultOgImage', '/images/og/coreflare.png')
            ->assertSet('seoIndexSite', false)
            ->assertSet('seoGoogleSiteVerification', 'google-token')
            ->assertSet('seoBingSiteVerification', 'bing-token')
            ->assertSet('savedSection', 'seo');

        $snapshot = app(GetSystemSettings::class)->handle();

        self::assertSame('Coreflare Platform', $snapshot->siteName);
        self::assertSame('از سرور تا سرویس', $snapshot->tagline);
        self::assertSame('Coreflare | مدیریت زیرساخت', $snapshot->seoDefaultTitle);
        self::assertSame('مدیریت سرور و سرویس‌ها از یک محیط واحد.', $snapshot->seoDefaultDescription);
        self::assertSame('/images/og/coreflare.png', $snapshot->seoDefaultOgImage);
        self::assertFalse($snapshot->seoIndexSite);
        self::assertSame('google-token', $snapshot->seoGoogleSiteVerification);
        self::assertSame('bing-token', $snapshot->seoBingSiteVerification);
    }

    public function test_invalid_seo_title_is_rejected_before_persistence(): void
    {
        $this->actingAs($this->admin());

        $before = app(GetSystemSettings::class)->handle();

        Livewire::test(Index::class)
            ->set('seoDefaultTitle', str_repeat('x', 71))
            ->call('saveSeo')
            ->assertHasErrors(['seoDefaultTitle' => 'max']);

        $after = app(GetSystemSettings::class)->handle();

        self::assertEquals($before, $after);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }
}
