<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.site_alternate_name', 'کورفلر');
        $this->migrator->add('seo.organization_logo', null);
        $this->migrator->add('seo.favicon', null);
        $this->migrator->add('seo.apple_touch_icon', null);

        $this->migrator->update(
            'seo.default_title',
            static fn (string $current): string => $current === 'Coreflare | از سرور تا سرویس، در یک پنل'
                ? 'Coreflare | مدیریت VPS و راه‌اندازی سرویس‌ها'
                : $current,
        );

        $this->migrator->update(
            'seo.default_description',
            static fn (string $current): string => $current === 'Coreflare محیطی یکپارچه برای اتصال یا تهیه VPS، راه‌اندازی سرویس‌ها و مدیریت زیرساخت است.'
                ? 'Coreflare پلتفرمی برای تهیه یا اتصال VPS، راه‌اندازی سرویس‌هایی مانند WordPress و n8n و مدیریت سرور، دامنه و زیرساخت از یک پنل است.'
                : $current,
        );
    }
};
