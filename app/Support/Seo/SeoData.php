<?php

declare(strict_types=1);

namespace App\Support\Seo;

final readonly class SeoData
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $siteName,
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots,
        public string $type = 'website',
        public ?string $image = null,
        public ?string $favicon = null,
        public ?string $appleTouchIcon = null,
        public string $locale = 'fa_IR',
        public ?string $publishedTime = null,
        public ?string $modifiedTime = null,
        public array $schema = [],
        public ?string $googleSiteVerification = null,
        public ?string $bingSiteVerification = null,
    ) {}
}
