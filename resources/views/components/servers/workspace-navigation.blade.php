@props([
    'server',
])

@php
    $items = [
        [
            'label' => 'نمای کلی',
            'icon' => 'lucide.layout-dashboard',
            'route' => route(
                'panel.servers.dashboard',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.dashboard',
            ),
        ],

        [
            'label' => 'برنامه‌ها',
            'icon' => 'lucide.package',
            'route' => route(
                'panel.servers.applications.index',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.applications.*',
            ),
        ],
    ];
@endphp

<nav
    class="border-t border-base-300 px-3 sm:px-4"
    aria-label="ناوبری سرور"
>
    <div
        class="flex items-center gap-1 overflow-x-auto"
    >
        @foreach ($items as $item)

            <a
                href="{{ $item['route'] }}"
                wire:navigate
                @class([
                    'relative flex shrink-0 items-center gap-2',
                    'px-3 py-3.5 text-sm font-medium',
                    'transition-colors',

                    'text-primary' => $item['active'],

                    'text-base-content/55 hover:text-base-content'
                        => ! $item['active'],
                ])
            >
                <x-icon
                    :name="$item['icon']"
                    class="size-4"
                />

                <span>
                    {{ $item['label'] }}
                </span>

                @if ($item['active'])

                    <span
                        class="absolute inset-x-3 bottom-0
                               h-0.5 rounded-full bg-primary"
                    ></span>

                @endif
            </a>

        @endforeach
    </div>
</nav>
