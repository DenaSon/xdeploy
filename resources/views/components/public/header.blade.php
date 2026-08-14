@props([
    'showNavigation' => true,
])

@php
    $productName = config('app.name');

    $isLanding = request()->is('/');
    $showLandingNavigation = $showNavigation && $isLanding;

    $isAuthenticated = auth()->check();

    $primaryUrl = $isAuthenticated
        ? route('panel.servers.index')
        : route('login');

    $primaryLabel = $isAuthenticated
        ? 'پنل مدیریت'
        : 'شروع استفاده';

    $primaryIcon = $isAuthenticated
        ? 'lucide.layout-dashboard'
        : 'lucide.arrow-left';
@endphp


<header
    x-data="{
        scrolled: {{ $isLanding ? 'window.scrollY > 12' : 'true' }}
    }"

    @if($isLanding)
        @scroll.window="scrolled = window.scrollY > 12"
    @endif

    :class="scrolled
        ? 'border-base-300/70 bg-base-100/85 shadow-sm shadow-base-content/[0.025] backdrop-blur-xl'
        : 'border-transparent bg-base-100/70 backdrop-blur-md'"

    class="
        sticky top-0 z-40

        border-b

        transition-[background-color,border-color,box-shadow]
        duration-300
    "
>
    <div
        class="
            mx-auto

            flex h-16
            w-full max-w-7xl
            items-center

            px-4

            sm:px-6

            lg:px-8
        "
    >
        {{-- Brand --}}
        <a
            href="{{ url('/') }}"
            wire:navigate
            aria-label="{{ $productName }}"
            class="
                group

                flex min-w-0
                shrink-0
                items-center gap-2.5
            "
        >
            <span
                class="
                    flex size-9 shrink-0
                    items-center justify-center

                    rounded-xl

                    bg-primary/10
                    text-primary

                    ring-1 ring-primary/10

                    transition-all
                    duration-200

                    group-hover:bg-primary/15
                    group-hover:ring-primary/15
                "
            >
                <x-icon
                    name="lucide.server-cog"
                    class="!size-[18px] stroke-[1.8]"
                />
            </span>

            <span
                dir="ltr"
                class="
                    truncate

                    text-[15px]
                    font-semibold
                    tracking-tight
                    text-base-content
                "
            >
                {{ $productName }}
            </span>
        </a>


        {{-- Landing navigation --}}
        @if($showLandingNavigation)
            <div
                class="
                    ms-6

                    hidden
                    items-center

                    lg:flex
                "
            >
                {{-- Separator --}}
                <span
                    aria-hidden="true"
                    class="
                        me-5

                        select-none

                        text-sm
                        font-light
                        text-base-content/15
                    "
                >
                    |
                </span>


                <nav
                    aria-label="ناوبری صفحه اصلی"
                    class="
                        flex
                        items-center gap-1
                    "
                >
                    {{-- How it works --}}
                    <a
                        href="#how-it-works"
                        @click.prevent="
                            document.querySelector('#how-it-works')
                                ?.scrollIntoView({
                                    behavior: window.matchMedia(
                                        '(prefers-reduced-motion: reduce)'
                                    ).matches
                                        ? 'auto'
                                        : 'smooth',
                                    block: 'start'
                                })
                        "
                        class="
                            rounded-lg

                            px-3 py-2

                            text-xs
                            font-medium
                            text-base-content/50

                            transition-colors
                            duration-150

                            hover:bg-base-200/60
                            hover:text-base-content
                        "
                    >
                        نحوه عملکرد
                    </a>


                    <span
                        aria-hidden="true"
                        class="
                            mx-1

                            select-none

                            text-xs
                            font-light
                            text-base-content/15
                        "
                    >
                        |
                    </span>


                    {{-- Capabilities --}}
                    <a
                        href="#capabilities"
                        @click.prevent="
                            document.querySelector('#capabilities')
                                ?.scrollIntoView({
                                    behavior: window.matchMedia(
                                        '(prefers-reduced-motion: reduce)'
                                    ).matches
                                        ? 'auto'
                                        : 'smooth',
                                    block: 'start'
                                })
                        "
                        class="
                            rounded-lg

                            px-3 py-2

                            text-xs
                            font-medium
                            text-base-content/50

                            transition-colors
                            duration-150

                            hover:bg-base-200/60
                            hover:text-base-content
                        "
                    >
                        قابلیت‌ها
                    </a>
                </nav>
            </div>
        @endif


        {{-- Spacer --}}
        <div class="flex-1"></div>


        {{-- Actions --}}
        <div
            class="
                flex shrink-0
                items-center gap-2
            "
        >
            {{-- Primary CTA --}}
            <x-button
                :label="$primaryLabel"
                :icon="$primaryIcon"
                :link="$primaryUrl"
                wire:navigate
                class="
                    btn-primary
                    btn-sm

                    rounded-xl

                    px-4

                    font-medium

                    shadow-sm
                    shadow-primary/10
                "
            />


            {{-- Separator --}}
            <span
                aria-hidden="true"
                class="
                    hidden

                    select-none

                    text-sm
                    font-light
                    text-base-content/15

                    sm:inline
                "
            >
                |
            </span>


            {{-- Theme --}}
            <x-theme-toggle
                class="
                    btn
                    btn-square
                    btn-ghost
                    btn-sm

                    rounded-xl

                    text-base-content/50

                    hover:bg-base-200/70
                    hover:text-base-content
                "
            />
        </div>
    </div>
</header>
