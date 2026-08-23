<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentationCategory;
use Illuminate\Database\Seeder;

final class DocumentationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'title' => 'شروع کار',
                'slug' => 'getting-started',
                'description' => 'راهنمای شروع استفاده از Coreflare، ورود به پنل، آشنایی با محیط و راه‌اندازی اولین سرور.',
                'sort_order' => 10,
                'is_published' => true,
            ],
            [
                'title' => 'سرورها و VPS',
                'slug' => 'servers',
                'description' => 'راهنمای خرید یا اتصال VPS، مدیریت سرورها، دسترسی SSH، بررسی وضعیت و منابع و انجام عملیات مرتبط با سرور.',
                'sort_order' => 20,
                'is_published' => true,
            ],
            [
                'title' => 'برنامه‌ها و سرویس‌ها',
                'slug' => 'applications',
                'description' => 'راهنمای نصب، راه‌اندازی و مدیریت برنامه‌ها و سرویس‌هایی مانند Marzban، n8n و WordPress.',
                'sort_order' => 30,
                'is_published' => true,
            ],
            [
                'title' => 'دامنه و دسترسی عمومی',
                'slug' => 'domains',
                'description' => 'راهنمای اتصال دامنه، فعال‌سازی دسترسی عمومی سرویس‌ها، تنظیم DNS و HTTPS و عیب‌یابی ارتباط دامنه با سرور.',
                'sort_order' => 40,
                'is_published' => true,
            ],
            [
                'title' => 'حساب و تنظیمات',
                'slug' => 'account-integrations',
                'description' => 'راهنمای تنظیمات حساب، امنیت و Passkey، اعلان‌ها و اتصال Coreflare به سرویس‌هایی مانند Cloudflare و Telegram.',
                'sort_order' => 50,
                'is_published' => true,
            ],
        ];

        foreach ($categories as $category) {
            DocumentationCategory::query()->updateOrCreate(
                [
                    'slug' => $category['slug'],
                ],
                $category,
            );
        }
    }
}
