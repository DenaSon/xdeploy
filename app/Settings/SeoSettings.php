<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class SeoSettings extends Settings
{
    public string $default_title;

    public string $default_description;

    public ?string $default_og_image;

    public string $site_alternate_name;

    public ?string $organization_logo;

    public ?string $favicon;

    public ?string $apple_touch_icon;

    public bool $index_site;

    public ?string $google_site_verification;

    public ?string $bing_site_verification;

    public static function group(): string
    {
        return 'seo';
    }
}
