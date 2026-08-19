@props([
    'server',
])

@inject('cloudServerCapabilities', 'App\\Application\\Cloud\\Servers\\CloudServerCapabilityResolver')

@php
    $isActive = $server->isActive();

    $statusLabel = $isActive
        ? 'آماده'
        : 'غیرفعال';

    $statusClasses = $isActive
        ? 'bg-success/10 text-success'
        : 'bg-base-200 text-base-content/45';

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
            'label' => 'مشخصات',
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
            'icon' => 'lucide.blocks',
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


    $canUseConsole = $server->isCloudProvisioned()
        && $cloudServerCapabilities->supports(
            server: $server,
            capability: \\App\\Domain\\Cloud\\Contracts\\CloudServerConsoleInterface::class,
        );

    if ($canUseConsole) {
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
    x-data
    x-init="
        $nextTick(() => {
            $refs.activeTab?.scrollIntoView({
                behavior: 'auto',
                block: 'nearest',
                inline: 'center'
            })
        })
    "
    class="
        overflow-hidden
        rounded-2xl

        border border-base-300/80
        bg-base-100

        shadow-sm
        shadow-base-content/[0.015]
    "
>
    {{-- Server context / desktop navigation --}}
    <div
        class="
            flex
            items-center
            justify-between
            gap-3

            px-3 py-2.5

            sm:px-4

            xl:min-h-[58px]
            xl:gap-5
            xl:px-5
        "
    >
        {{-- Desktop navigation --}}
        <nav
            aria-label="ناوبری سرور"
            class="
                hidden
                min-w-0

                xl:block
            "
        >
            <div
                class="
                    flex
                    items-center gap-1

                    rounded-xl
                    bg-base-200/55

                    p-1
                "
            >
                @foreach($navigationItems as $item)
                    <a
                        href="{{ $item['route'] }}"
                        wire:navigate
                        @if($item['active'])
                            aria-current="page"
                        @endif
                        @class([
                            '
                                inline-flex
                                shrink-0
                                items-center gap-1.5

                                rounded-lg

                                px-3 py-1.5

                                text-xs
                                font-medium

                                transition-all
                                duration-150
                            ',

                            '
                                bg-base-100
                                font-semibold
                                text-primary

                                shadow-sm
                                shadow-base-content/[0.035]

                                ring-1
                                ring-base-300/70
                            ' => $item['active'],

                            '
                                text-base-content/60

                                hover:bg-base-100/70
                                hover:text-base-content
                            ' => ! $item['active'],
                        ])
                    >
                        <x-icon
                            :name="$item['icon']"
                            @class([
                                '
                                    !size-3.5
                                    stroke-[1.8]
                                ',
                                'text-primary' => $item['active'],
                                'text-base-content/55' => ! $item['active'],
                            ])
                        />

                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>


        {{-- Server context --}}
        <div
            class="
                flex min-w-0
                shrink-0
                items-center gap-2.5

                xl:max-w-[34%]
            "
        >
            {{-- Server icon --}}
            <div
                @class([
                    '
                        flex size-8 shrink-0
                        items-center justify-center

                        rounded-xl

                        sm:size-9
                    ',
                    '
                        bg-primary/[0.08]
                        text-primary
                    ' => $server->isCloudProvisioned(),
                    '
                        bg-base-200/70
                        text-base-content/50
                    ' => ! $server->isCloudProvisioned(),
                ])
            >
                <x-icon
                    :name="$server->isCloudProvisioned()
                        ? 'lucide.cloud'
                        : 'lucide.server'"
                    class="!size-4 stroke-[1.8]"
                />
            </div>


            {{-- Server identity --}}
            <div class="min-w-0">
                <div
                    class="
                        flex min-w-0
                        items-center gap-1.5
                    "
                >
                    <h1
                        dir="ltr"
                        class="
                            technical-value

                            min-w-0
                            truncate

                            text-[13px]
                            font-semibold
                            tracking-tight
                            text-base-content

                            sm:text-sm
                        "
                    >
                        {{ $server->name }}
                    </h1>


                    <span
                        class="
                            inline-flex
                            shrink-0
                            items-center gap-1

                            rounded-full

                            px-1.5 py-0.5

                            text-[9px]
                            font-medium

                            {{ $statusClasses }}
                        "
                    >
                        <span
                            @class([
                                '
                                    size-1
                                    rounded-full
                                ',
                                'bg-success' => $isActive,
                                'bg-base-content/30' => ! $isActive,
                            ])
                        ></span>

                        {{ $statusLabel }}
                    </span>
                </div>


                <div
                    class="
                        mt-0.5

                        flex min-w-0
                        items-center gap-1

                        text-[10px]
                        text-base-content/40
                    "
                >
                    <x-icon
                        name="lucide.network"
                        class="
                            !size-2.5
                            shrink-0
                            stroke-[1.6]
                        "
                    />

                    <span
                        dir="ltr"
                        class="
                            technical-value
                            truncate
                            text-base-content/50
                        "
                    >
                        {{ $connectionAddress }}
                    </span>
                </div>
            </div>
        </div>
    </div>


    {{-- Mobile / tablet navigation --}}
    <nav
        aria-label="ناوبری سرور"
        class="
            border-t
            border-base-300/60
            bg-base-200/20

            xl:hidden
        "
    >
        <div
            class="
                overflow-x-auto
                overscroll-x-contain
                scroll-smooth

                px-2 py-1.5

                [scrollbar-width:none]
                [-webkit-overflow-scrolling:touch]
                [&::-webkit-scrollbar]:hidden

                sm:px-3
            "
        >
            <div
                class="
                    flex
                    w-max
                    min-w-full
                    items-center gap-1
                "
            >
                @foreach($navigationItems as $item)
                    <a
                        href="{{ $item['route'] }}"
                        wire:navigate

                        @if($item['active'])
                            x-ref="activeTab"
                            aria-current="page"
                        @endif

                        @class([
                            '
                                inline-flex
                                min-h-10
                                shrink-0
                                items-center gap-1.5

                                rounded-xl

                                px-2.5 py-1.5

                                text-[11px]
                                font-medium

                                transition-all
                                duration-150

                                sm:px-3
                                sm:text-xs
                            ',

                            '
                                bg-primary/[0.08]
                                font-semibold
                                text-primary

                                ring-1
                                ring-primary/15
                            ' => $item['active'],

                            '
                                text-base-content/55

                                hover:bg-base-200/80
                                hover:text-base-content
                            ' => ! $item['active'],
                        ])
                    >
                        <x-icon
                            :name="$item['icon']"
                            @class([
                                '
                                    !size-3.5
                                    stroke-[1.8]

                                    sm:!size-4
                                ',
                                'text-primary' => $item['active'],
                                'text-base-content/50' => ! $item['active'],
                            ])
                        />

                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>
</header>
