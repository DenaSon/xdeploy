<?php

namespace App\Application\Navigation;

class PanelNavigation
{
    public static function items(): array
    {
        return [
            [
                'title' => 'داشبورد',
                'icon'  => 'lucide.layout-dashboard',
                'route' => route('panel.dashboard'),
                'name'  => 'panel.dashboard',
            ],

            [
                'title' => 'ماژول‌ها',
                'icon'  => 'lucide.package',
                'route' => route('panel.modules.index'),
                'name'  => 'panel.modules.*',
            ],

            [
                'title' => 'تنظیمات',
                'icon'  => 'lucide.settings',
                'route' => route('panel.dashboard'),
                'name'  => 'panel.settings.*',
            ],
        ];
    }
}
