@props([
    'title' => null,
])

@php
    $activeServer = auth()->user()
        ?->servers()
        ->active()
        ->first();
@endphp

<header
    class="
        navbar sticky top-0 z-20
        h-14 min-h-14

        border-b border-base-content/5
        bg-base-100/85
        backdrop-blur-xl

        px-3
        lg:px-5
    "
>
    {{-- Start --}}
    <div class="navbar-start min-w-0 gap-2">

        <label
            for="panel-drawer"
            aria-label="باز کردن منوی پنل"
            class="
                btn btn-square btn-ghost btn-sm
                shrink-0 rounded-xl
                lg:hidden
            "
        >
            <x-icon
                name="lucide.menu"
                class="size-5"
            />
        </label>

        @auth
            @if($activeServer)

                <a
                    href="{{ route('panel.servers.dashboard', $activeServer) }}"
                    wire:navigate
                    aria-label="مدیریت سرور فعال {{ $activeServer->name }}"
                    class="
                        group
                        flex min-w-0 items-center gap-2
                        rounded-xl

                        border border-base-content/5
                        bg-base-200/35

                        px-2.5 py-1.5

                        transition-all duration-200

                        hover:border-primary/15
                        hover:bg-primary/5
                    "
                >
                    {{-- Server icon --}}
                    <span
                        class="
                            flex size-6 shrink-0
                            items-center justify-center
                            rounded-lg

                            bg-primary/10
                            text-primary

                            transition-colors
                            group-hover:bg-primary/15
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-3 stroke-[1.5]"
                        />
                    </span>

                    {{-- Server information --}}
                    <span class="flex min-w-0 items-center gap-2">

                        <span class="min-w-0 text-start">

                            <span
                                class="
                                    block max-w-28 truncate
                                    text-[11px] font-medium
                                    leading-none
                                    text-base-content/65

                                    sm:max-w-40
                                "
                            >
                                {{ $activeServer->name }}
                            </span>

                            <span
                                dir="ltr"
                                class="
                                    mt-1 block
                                    max-w-28 truncate
                                    font-mono text-[9px]
                                    leading-none
                                    text-base-content/35

                                    sm:max-w-40
                                "
                            >
                                {{ $activeServer->host }}
                            </span>

                        </span>

                        {{-- Active indicator --}}
                        <span
                            class="
                                inline-grid shrink-0
                                *:[grid-area:1/1]
                            "
                            aria-label="سرور فعال"
                        >
                            <span
                                class="
                                    status status-success
                                    animate-ping
                                "
                            ></span>

                            <span
                                class="
                                    status status-success
                                "
                            ></span>
                        </span>

                    </span>
                </a>

            @elseif($title)

                <span
                    class="
                        truncate text-sm font-medium
                        text-base-content/60
                    "
                >
                    {{ $title }}
                </span>

            @endif
        @endauth

    </div>

    {{-- End --}}
    <div class="navbar-end gap-1">

        <x-theme-toggle />

        <x-panel.user-menu />

    </div>
</header>
