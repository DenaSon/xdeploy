<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Coreflare');

        $this->migrator->add('branding.tagline', 'از سرور تا سرویس، در یک پنل');

        $this->migrator->add('seo.default_title', 'Coreflare | از سرور تا سرویس، در یک پنل');
        $this->migrator->add(
            'seo.default_description',
            'Coreflare محیطی یکپارچه برای اتصال یا تهیه VPS، راه‌اندازی سرویس‌ها و مدیریت زیرساخت است.',
        );
        $this->migrator->add('seo.default_og_image', null);
        $this->migrator->add('seo.index_site', true);
    }
};
