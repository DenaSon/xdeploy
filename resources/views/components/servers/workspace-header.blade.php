@props([
    'server',
])

@inject('cloudServerCapabilities', 'App\Application\Cloud\Servers\CloudServerCapabilityResolver')

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
            capability: \App\Domain\Cloud\Contracts\CloudServerConsoleInterface::class,
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
    {{-- Desktop context --}}
    <div
        class="
            flex
            items-center
            justify-between
            gap-5

            px-4 py-2.5

            sm:px-5

            lg:min-h-[58px]
        "
    >
        {{-- Desktop navigation --}}
        <nav
            aria-label="ناوبری سرور"
            class="
                hidden
                min-w-0

                lg:block
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

                lg:max-w-[34%]
            "
        >
            {{-- Server icon --}}
            <div
                @class([
                    '
                        flex size-9 shrink-0
                        items-center justify-center

                        rounded-xl
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

                            text-sm
                            font-semibold
                            tracking-tight
                            text-base-content
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

            lg:hidden
        "
    >
        <div
            class="
                overflow-x-auto

                px-2 py-2

                [scrollbar-width:none]
                [&::-webkit-scrollbar]:hidden
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
                                shrink-0
                                items-center gap-1.5

                                rounded-xl

                                px-3 py-2

                                text-xs
                                font-medium

                                transition-colors
                                duration-150
                            ',

                            '
                                bg-primary/10
                                font-semibold
                                text-primary
                            ' => $item['active'],

                            '
                                text-base-content/60

                                hover:bg-base-200/70
                                hover:text-base-content
                            ' => ! $item['active'],
                        ])
                    >
                        <x-icon
                            :name="$item['icon']"
                            @class([
                                '
                                    !size-4
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
        </div>
    </nav>
</header>
