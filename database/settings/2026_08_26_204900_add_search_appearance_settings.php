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
    }
};
