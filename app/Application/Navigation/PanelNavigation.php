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
                'title' => 'برنامه ها',
                'icon' => 'lucide.package',
                'route' => route('panel.applications.index'),
                'name' => 'panel.applications.*',
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
