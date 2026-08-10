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
                'description' => 'Marzban یک پنل مدیریت Xray است. xDeploy نصب، راه‌اندازی و مدیریت چرخه اجرای آن را روی سرور انجام می‌دهد.',
                'icon' => 'lucide.shield-check',
                'is_published' => true,
                'sort_order' => 10,
            ],
        );
    }
}
