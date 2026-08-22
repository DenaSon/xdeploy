<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;

final class GetSystemSettings
{
    public function __construct(
        private readonly GeneralSettings $general,
        private readonly BrandingSettings $branding,
        private readonly SeoSettings $seo,
    ) {}

    public function handle(): SystemSettingsSnapshot
    {
        return new SystemSettingsSnapshot(
            siteName: $this->general->site_name,
            tagline: $this->branding->tagline,
            seoDefaultTitle: $this->seo->default_title,
            seoDefaultDescription: $this->seo->default_description,
            seoDefaultOgImage: $this->seo->default_og_image,
            seoIndexSite: $this->seo->index_site,
            seoGoogleSiteVerification: $this->seo->google_site_verification,
            seoBingSiteVerification: $this->seo->bing_site_verification,
        );
    }
}
