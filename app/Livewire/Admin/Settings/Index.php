<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Application\Settings\GetSystemSettings;
use App\Application\Settings\StoreSeoAsset;
use App\Application\Settings\SystemSettingsSnapshot;
use App\Application\Settings\UpdateBrandingSettings;
use App\Application\Settings\UpdateGeneralSettings;
use App\Application\Settings\UpdateSeoSettings;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('تنظیمات سامانه')]
final class Index extends Component
{
    use WithFileUploads;

    public string $siteName = '';

    public string $tagline = '';

    public string $seoDefaultTitle = '';

    public string $seoDefaultDescription = '';

    public string $seoDefaultOgImage = '';

    public string $seoSiteAlternateName = '';

    public string $seoOrganizationLogo = '';

    public string $seoFavicon = '';

    public string $seoAppleTouchIcon = '';

    public $seoDefaultOgImageUpload = null;

    public $seoOrganizationLogoUpload = null;

    public $seoFaviconUpload = null;

    public $seoAppleTouchIconUpload = null;

    public bool $seoIndexSite = true;

    public string $seoGoogleSiteVerification = '';

    public string $seoBingSiteVerification = '';

    public ?string $savedSection = null;

    public function mount(GetSystemSettings $settings): void
    {
        $this->hydrateFromSnapshot($settings->handle());
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'siteName',
            'tagline',
            'seoDefaultTitle',
            'seoDefaultDescription',
            'seoDefaultOgImage',
            'seoSiteAlternateName',
            'seoOrganizationLogo',
            'seoFavicon',
            'seoAppleTouchIcon',
            'seoDefaultOgImageUpload',
            'seoOrganizationLogoUpload',
            'seoFaviconUpload',
            'seoAppleTouchIconUpload',
            'seoIndexSite',
            'seoGoogleSiteVerification',
            'seoBingSiteVerification',
        ], true)) {
            $this->savedSection = null;
        }
    }

    public function saveGeneral(UpdateGeneralSettings $update): void
    {
        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:80'],
        ]);

        $this->siteName = trim($validated['siteName']);

        $update->handle($this->actor(), [
            'site_name' => $this->siteName,
        ]);

        $this->savedSection = 'general';
    }

    public function saveBranding(UpdateBrandingSettings $update): void
    {
        $validated = $this->validate([
            'tagline' => ['required', 'string', 'max:120'],
        ]);

        $this->tagline = trim($validated['tagline']);

        $update->handle($this->actor(), [
            'tagline' => $this->tagline,
        ]);

        $this->savedSection = 'branding';
    }

    public function saveSeo(
        UpdateSeoSettings $update,
        StoreSeoAsset $assets,
    ): void {
        $validated = $this->validate([
            'seoDefaultTitle' => ['required', 'string', 'max:70'],
            'seoDefaultDescription' => ['required', 'string', 'max:160'],
            'seoDefaultOgImage' => ['nullable', 'string', 'max:2048'],
            'seoSiteAlternateName' => ['required', 'string', 'max:80'],
            'seoOrganizationLogo' => ['nullable', 'string', 'max:2048'],
            'seoFavicon' => ['nullable', 'string', 'max:2048'],
            'seoAppleTouchIcon' => ['nullable', 'string', 'max:2048'],
            'seoDefaultOgImageUpload' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:5120',
                'dimensions:min_width=600,min_height=315',
            ],
            'seoOrganizationLogoUpload' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:min_width=112,min_height=112',
            ],
            'seoFaviconUpload' => [
                'nullable',
                'image',
                'mimes:png',
                'max:1024',
                'dimensions:ratio=1/1,min_width=48,min_height=48',
            ],
            'seoAppleTouchIconUpload' => [
                'nullable',
                'image',
                'mimes:png',
                'max:2048',
                'dimensions:ratio=1/1,min_width=180,min_height=180',
            ],
            'seoIndexSite' => ['required', 'boolean'],
            'seoGoogleSiteVerification' => ['nullable', 'string', 'max:255'],
            'seoBingSiteVerification' => ['nullable', 'string', 'max:255'],
        ]);

        $this->seoDefaultTitle = trim($validated['seoDefaultTitle']);
        $this->seoDefaultDescription = trim($validated['seoDefaultDescription']);
        $this->seoDefaultOgImage = trim($validated['seoDefaultOgImage'] ?? '');
        $this->seoSiteAlternateName = trim($validated['seoSiteAlternateName']);
        $this->seoOrganizationLogo = trim($validated['seoOrganizationLogo'] ?? '');
        $this->seoFavicon = trim($validated['seoFavicon'] ?? '');
        $this->seoAppleTouchIcon = trim($validated['seoAppleTouchIcon'] ?? '');
        $this->seoGoogleSiteVerification = trim($validated['seoGoogleSiteVerification'] ?? '');
        $this->seoBingSiteVerification = trim($validated['seoBingSiteVerification'] ?? '');

        $actor = $this->actor();

        if ($this->seoDefaultOgImageUpload !== null) {
            $this->seoDefaultOgImage = $assets->handle(
                $actor,
                $this->seoDefaultOgImageUpload,
                'open-graph',
                $this->seoDefaultOgImage,
            );
        }

        if ($this->seoOrganizationLogoUpload !== null) {
            $this->seoOrganizationLogo = $assets->handle(
                $actor,
                $this->seoOrganizationLogoUpload,
                'organization-logo',
                $this->seoOrganizationLogo,
            );
        }

        if ($this->seoFaviconUpload !== null) {
            $this->seoFavicon = $assets->handle(
                $actor,
                $this->seoFaviconUpload,
                'favicon',
                $this->seoFavicon,
            );
        }

        if ($this->seoAppleTouchIconUpload !== null) {
            $this->seoAppleTouchIcon = $assets->handle(
                $actor,
                $this->seoAppleTouchIconUpload,
                'apple-touch-icon',
                $this->seoAppleTouchIcon,
            );
        }

        $update->handle($actor, [
            'default_title' => $this->seoDefaultTitle,
            'default_description' => $this->seoDefaultDescription,
            'default_og_image' => $this->seoDefaultOgImage,
            'site_alternate_name' => $this->seoSiteAlternateName,
            'organization_logo' => $this->seoOrganizationLogo,
            'favicon' => $this->seoFavicon,
            'apple_touch_icon' => $this->seoAppleTouchIcon,
            'index_site' => $this->seoIndexSite,
            'google_site_verification' => $this->seoGoogleSiteVerification,
            'bing_site_verification' => $this->seoBingSiteVerification,
        ]);

        $this->reset([
            'seoDefaultOgImageUpload',
            'seoOrganizationLogoUpload',
            'seoFaviconUpload',
            'seoAppleTouchIconUpload',
        ]);

        $this->savedSection = 'seo';
    }

    public function render(): View
    {
        return view('livewire.admin.settings.index');
    }

    private function hydrateFromSnapshot(SystemSettingsSnapshot $snapshot): void
    {
        $this->siteName = $snapshot->siteName;
        $this->tagline = $snapshot->tagline;
        $this->seoDefaultTitle = $snapshot->seoDefaultTitle;
        $this->seoDefaultDescription = $snapshot->seoDefaultDescription;
        $this->seoDefaultOgImage = $snapshot->seoDefaultOgImage ?? '';
        $this->seoSiteAlternateName = $snapshot->seoSiteAlternateName;
        $this->seoOrganizationLogo = $snapshot->seoOrganizationLogo ?? '';
        $this->seoFavicon = $snapshot->seoFavicon ?? '';
        $this->seoAppleTouchIcon = $snapshot->seoAppleTouchIcon ?? '';
        $this->seoIndexSite = $snapshot->seoIndexSite;
        $this->seoGoogleSiteVerification = $snapshot->seoGoogleSiteVerification ?? '';
        $this->seoBingSiteVerification = $snapshot->seoBingSiteVerification ?? '';
    }

    /**
     * @throws AuthenticationException
     */
    private function actor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }
}
