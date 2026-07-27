<?php

namespace App\Application\Navigation;

class PanelNavigation
{
    public static function items(): array
    {
        return [
            [
                'title' => __('panel.dashboard'),
                'icon' => 'lucide.layout-dashboard',
                'route' => route('panel.dashboard'),
            ],

            [
                'title' => __('panel.modules'),
                'icon' => 'lucide.package',
                'route' => route('panel.dashboard'),
            ],

            [
                'title' => __('panel.settings'),
                'icon' => 'lucide.settings',
                'route' => route('panel.dashboard'),
            ],
        ];
    }
}
