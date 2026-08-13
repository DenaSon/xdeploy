<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApplicationCatalogItem;
use Illuminate\Database\Seeder;

final class ApplicationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        ApplicationCatalogItem::query()->updateOrCreate(
            [
                'slug' => 'marzban',
            ],
            [
                'name' => 'Marzban',
                'short_description' => 'پنل مدیریت کاربران و سرویس‌های مبتنی بر Xray',
                'description' => 'Marzban یک پنل متن‌باز برای مدیریت کاربران و سرویس‌های مبتنی بر Xray است که امکاناتی مانند مدیریت دسترسی‌ها، مصرف ترافیک، محدودیت کاربران و پروتکل‌های مختلف اتصال را فراهم می‌کند.',
                'icon' => 'lucide.shield-check',
                'is_published' => true,
                'sort_order' => 10,
            ],
        );

        ApplicationCatalogItem::query()->updateOrCreate(
            [
                'slug' => 'n8n',
            ],
            [
                'name' => 'n8n',
                'short_description' => 'پلتفرم اتوماسیون و ساخت گردش‌کار بین سرویس‌ها و APIها',
                'description' => 'n8n یک پلتفرم متن‌باز برای ساخت و اجرای گردش‌کارهای خودکار است که امکان اتصال سرویس‌ها، APIها، پایگاه‌های داده و ابزارهای مختلف را بدون نیاز به توسعه یکپارچه‌سازی‌های پیچیده فراهم می‌کند.',
                'icon' => 'lucide.workflow',
                'is_published' => true,
                'sort_order' => 20,
            ],
        );

        ApplicationCatalogItem::query()->updateOrCreate(
            [
                'slug' => 'amneziawg',
            ],
            [
                'name' => 'AmneziaWG',
                'short_description' => 'پروتکل VPN سریع و مقاوم در برابر شناسایی مبتنی بر WireGuard',
                'description' => 'AmneziaWG یک پروتکل VPN متن‌باز مبتنی بر WireGuard است که با افزودن روش‌های استتار ترافیک، شناسایی اتصال توسط سامانه‌های تحلیل عمیق بسته‌ها را دشوارتر می‌کند و در عین حال سرعت و سادگی WireGuard را حفظ می‌کند.',
                'icon' => 'lucide.shield',
                'is_published' => true,
                'sort_order' => 30,
            ],
        );
    }
}
