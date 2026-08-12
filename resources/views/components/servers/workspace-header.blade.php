@props([
    'server',
])

@php
    $isActive = $server->isActive();

    $statusLabel = $isActive
        ? 'آماده'
        : 'غیرفعال';

    $statusClasses = $isActive
        ? 'border-success/20 bg-success/10 text-success'
        : 'border-base-300 bg-base-200 text-base-content/55';

    $connectionAddress = $server->host
        ? $server->host . ':' . $server->port
        : '—';

    $navigationItems = [
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
            'label' => 'اطلاعات',
            'icon' => 'lucide.server-cog',
            'route' => route(
                'panel.servers.details',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.details',
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

        [
            'label' => 'دامنه‌ها',
            'icon' => 'lucide.globe-2',
            'route' => route(
                'panel.servers.domains.index',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.domains.*',
            ),
        ],
    ];

    if ($server->isCloudProvisioned()) {
        $navigationItems[] = [
            'label' => 'کنسول',
            'icon' => 'lucide.monitor',
            'route' => route(
                'panel.servers.console',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.console',
            ),
        ];
    }

    $canRenew = $server->isCloudProvisioned()
        && $server->expires_at !== null
        && ! $server->hasExpired()
        && ! $server->isTerminated()
        && $server->termination_started_at === null;

    if ($canRenew) {
        $navigationItems[] = [
            'label' => 'تمدید',
            'icon' => 'lucide.calendar-plus',
            'route' => route(
                'panel.servers.renew',
                ['server' => $server],
            ),
            'active' => request()->routeIs(
                'panel.servers.renew',
            ),
        ];
    }
@endphp

<header
    class="px-4 py-3.5
           sm:px-5"
>
    <div
        class="flex flex-col gap-3
               lg:flex-row
               lg:items-center
               lg:justify-between"
    >
        {{-- Server navigation --}}
        <nav
            class="min-w-0"
            aria-label="ناوبری سرور"
        >
            <div
                class="flex items-center gap-1
                       overflow-x-auto"
            >
                @foreach ($navigationItems as $item)

                    <a
                        href="{{ $item['route'] }}"
                        wire:navigate
                        @if ($item['active'])
                            aria-current="page"
                        @endif
                        @class([
                            'flex shrink-0 items-center gap-1.5',
                            'rounded-xl px-3 py-2',
                            'text-sm font-medium',
                            'transition-colors duration-150',

                            'bg-primary/8 text-primary'
                                => $item['active'],

                            'text-base-content/50 hover:bg-base-200/60 hover:text-base-content/75'
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
                    </a>

                @endforeach
            </div>
        </nav>

        {{-- Server context --}}
        <div
            class="flex min-w-0
                   items-center gap-3
                   lg:max-w-[50%]"
        >
            {{-- Server icon --}}
            <div
                class="flex size-10 shrink-0
                       items-center justify-center
                       rounded-xl
                       border border-base-300
                       bg-base-200/50"
            >
                <x-icon
                    name="lucide.server"
                    class="size-4.5
                           text-base-content/60"
                />
            </div>

            {{-- Server identity --}}
            <div class="min-w-0">

                <div
                    class="flex flex-wrap
                           items-center gap-2"
                >
                    <h1
                        dir="ltr"
                        class="technical-value truncate
                               text-base font-semibold
                               tracking-tight
                               text-base-content"
                    >
                        {{ $server->name }}
                    </h1>

                    <span
                        class="inline-flex items-center
                               gap-1.5 rounded-full
                               border px-2 py-0.5
                               text-[11px] font-medium
                               {{ $statusClasses }}"
                    >
                        <span
                            @class([
                                'size-1.5 rounded-full',
                                'bg-success' => $isActive,
                                'bg-base-content/30' => ! $isActive,
                            ])
                        ></span>

                        {{ $statusLabel }}
                    </span>
                </div>

                <div
                    class="mt-1 flex items-center
                           gap-1.5
                           text-xs
                           text-base-content/45"
                >
                    <x-icon
                        name="lucide.network"
                        class="size-3.5 shrink-0"
                    />

                    <span
                        dir="ltr"
                        class="technical-value truncate"
                    >
                        {{ $connectionAddress }}
                    </span>
                </div>

            </div>
        </div>
    </div>
</header>
