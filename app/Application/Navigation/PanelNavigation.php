<?php

declare(strict_types=1);

namespace App\Application\Navigation;

class PanelNavigation
{
    public static function items(): array
    {
        $activeServer = auth()->user()
            ?->servers()
            ->active()
            ->first();

        return [
            [
                'title' => 'داشبورد',
                'icon' => 'lucide.layout-dashboard',
                'route' => $activeServer
                    ? route('panel.servers.dashboard', $activeServer)
                    : route('panel.servers.index'),
                'name' => 'panel.servers.dashboard',
            ],

            [
                'title' => 'سرورها',
                'icon' => 'lucide.server',
                'route' => route('panel.servers.index'),
                'name' => 'panel.servers.index',
            ],

            [
                'title' => 'برنامه‌ها',
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
