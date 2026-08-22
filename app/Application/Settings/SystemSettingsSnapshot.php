<?php

declare(strict_types=1);

namespace App\Application\Settings;

final readonly class SystemSettingsSnapshot
{
    public function __construct(
        public string $siteName,
        public string $tagline,
        public string $seoDefaultTitle,
        public string $seoDefaultDescription,
        public ?string $seoDefaultOgImage,
        public bool $seoIndexSite,
        public ?string $seoGoogleSiteVerification,
        public ?string $seoBingSiteVerification,
    ) {}
}
