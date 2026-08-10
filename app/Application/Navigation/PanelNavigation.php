<?php

declare(strict_types=1);

namespace App\Application\Navigation;

final class PanelNavigation
{
    /**
     * @return array<int, array{
     *     title: string,
     *     icon: string,
     *     route: string,
     *     name: string
     * }>
     */
    public static function items(): array
    {
        return [
            [
                'title' => 'سرورها',
                'icon' => 'lucide.server',
                'route' => route('panel.servers.index'),
                'name' => 'panel.servers.*',
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
