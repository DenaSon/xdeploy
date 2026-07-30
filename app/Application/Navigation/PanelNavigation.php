<?php

namespace App\Application\Navigation;

class PanelNavigation
{
    public static function items(): array
    {
        return [
            [
                'title' => 'داشبورد',
                'icon' => 'lucide.layout-dashboard',
                'route' => route('core.dashboard'),
                'name' => 'panel.dashboard',
            ],

            [
                'title' => 'ماژول‌ها',
                'icon' => 'lucide.package',
                'route' => route('core.dashboard'),
                'name' => 'panel.modules.*',
            ],

            [
                'title' => 'تنظیمات',
                'icon' => 'lucide.settings',
                'route' => route('core.dashboard'),
                'name' => 'panel.settings.*',
            ],
        ];
    }
}
