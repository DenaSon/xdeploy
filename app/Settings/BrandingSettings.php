<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class BrandingSettings extends Settings
{
    public string $tagline;

    public static function group(): string
    {
        return 'branding';
    }
}
