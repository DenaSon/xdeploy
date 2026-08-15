@props([
    'showNavigation' => true,
])

@php
    $productName = config('app.name');

    $isLanding = request()->is('/');
    $showPublicNavigation = (bool) $showNavigation;

    $documentationCategories = $showPublicNavigation
        ? app(App\Application\Navigation\PublicDocumentationNavigation::class)->categories()
        : [];

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
        scrolled: {{ $isLanding ? 'window.scrollY > 12' : 'true' }},
        desktopGuideOpen: false,
        mobileGuideOpen: false,

        closeGuideMenus() {
            this.desktopGuideOpen = false
            this.mobileGuideOpen = false
        },

        scrollToSection(id) {
            this.closeGuideMenus()

            document.querySelector(`#${id}`)?.scrollIntoView({
                behavior: window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches ? 'auto' : 'smooth',
                block: 'start',
            })
        },
    }"
    @keydown.escape.window="closeGuideMenus()"
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
            flex h-16 w-full max-w-7xl
            items-center
            px-4 sm:px-6 lg:px-8
        "
    >
        {{-- Brand --}}
        <a
            href="{{ route('home') }}"
            wire:navigate
            aria-label="{{ $productName }}"
            class="group flex min-w-0 shrink-0 items-center gap-2.5"
        >
            <span
                class="
                    flex size-9 shrink-0 items-center justify-center
                    rounded-xl
                    bg-primary/10 text-primary
                    ring-1 ring-primary/10
                    transition-all duration-200
                    group-hover:bg-primary/15 group-hover:ring-primary/15
                "
            >
                <x-icon
                    name="lucide.server-cog"
                    class="!size-[18px] stroke-[1.8]"
                />
            </span>

            <span
                dir="ltr"
                class="truncate text-[15px] font-semibold tracking-tight text-base-content"
            >
                {{ $productName }}
            </span>
        </a>

        {{-- Public navigation --}}
        @if($showPublicNavigation)
            <div class="ms-6 hidden items-center lg:flex">
                <span
                    aria-hidden="true"
                    class="me-5 select-none text-sm font-light text-base-content/15"
                >
                    |
                </span>

                <nav
                    aria-label="ناوبری عمومی"
                    class="flex items-center gap-1"
                >
                    <a
                        href="{{ route('home') }}#how-it-works"
                        @if($isLanding)
                            @click.prevent="scrollToSection('how-it-works')"
                        @endif
                        class="
                            rounded-lg px-3 py-2
                            text-xs font-medium text-base-content/50
                            transition-colors duration-150
                            hover:bg-base-200/60 hover:text-base-content
                        "
                    >
                        نحوه عملکرد
                    </a>

                    <span
                        aria-hidden="true"
                        class="mx-1 select-none text-xs font-light text-base-content/15"
                    >
                        |
                    </span>

                    <a
                        href="{{ route('home') }}#capabilities"
                        @if($isLanding)
                            @click.prevent="scrollToSection('capabilities')"
                        @endif
                        class="
                            rounded-lg px-3 py-2
                            text-xs font-medium text-base-content/50
                            transition-colors duration-150
                            hover:bg-base-200/60 hover:text-base-content
                        "
                    >
                        قابلیت‌ها
                    </a>

                    <span
                        aria-hidden="true"
                        class="mx-1 select-none text-xs font-light text-base-content/15"
                    >
                        |
                    </span>

                    <div
                        class="relative"
                        @click.outside="desktopGuideOpen = false"
                    >
                        <button
                            type="button"
                            @click="desktopGuideOpen = ! desktopGuideOpen"
                            :aria-expanded="desktopGuideOpen.toString()"
                            aria-controls="desktop-guidance-menu"
                            aria-haspopup="true"
                            class="
                                btn btn-ghost btn-sm
                                gap-2 rounded-xl border border-transparent px-3
                                text-xs font-medium text-base-content/55
                                hover:border-base-300/70 hover:bg-base-200/60 hover:text-base-content
                            "
                        >
                            <x-icon
                                name="lucide.book-open-text"
                                class="!size-4 stroke-[1.8]"
                            />

                            <span>راهنما</span>

                            <x-icon
                                name="lucide.chevron-down"
                                class="!size-3.5 stroke-[1.8] text-base-content/35 transition-transform duration-150"
                                x-bind:class="desktopGuideOpen ? 'rotate-180' : ''"
                            />
                        </button>

                        <div
                            id="desktop-guidance-menu"
                            x-show="desktopGuideOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                            style="display: none;"
                            class="
                                absolute right-0 top-full z-50 mt-2
                                w-[min(36rem,calc(100vw-2rem))]
                                origin-top-right overflow-hidden rounded-2xl
                                border border-base-300/80
                                bg-base-100/95
                                shadow-xl shadow-base-content/[0.08]
                                backdrop-blur-xl
                            "
                        >
                            <x-public.guidance-menu
                                :documentation-categories="$documentationCategories"
                            />
                        </div>
                    </div>
                </nav>
            </div>
        @endif

        <div class="flex-1"></div>

        {{-- Actions --}}
        <div class="flex shrink-0 items-center gap-2">
            @if($showPublicNavigation)
                <div
                    class="relative lg:hidden"
                    @click.outside="mobileGuideOpen = false"
                >
                    <button
                        type="button"
                        @click="mobileGuideOpen = ! mobileGuideOpen"
                        :aria-expanded="mobileGuideOpen.toString()"
                        aria-controls="mobile-guidance-menu"
                        aria-haspopup="true"
                        aria-label="بازکردن راهنما"
                        class="
                            btn btn-square btn-ghost btn-sm
                            rounded-xl text-base-content/50
                            hover:bg-base-200/70 hover:text-base-content
                        "
                    >
                        <x-icon
                            name="lucide.book-open-text"
                            class="!size-4 stroke-[1.8]"
                        />
                    </button>

                    <div
                        id="mobile-guidance-menu"
                        x-show="mobileGuideOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        style="display: none;"
                        class="
                            fixed inset-x-4 top-[4.5rem] z-50
                            max-h-[calc(100vh-5.5rem)] overflow-hidden rounded-2xl
                            border border-base-300/80
                            bg-base-100/95
                            shadow-xl shadow-base-content/[0.08]
                            backdrop-blur-xl
                        "
                    >
                        <x-public.guidance-menu
                            :documentation-categories="$documentationCategories"
                        />
                    </div>
                </div>
            @endif

            <x-button
                :label="$primaryLabel"
                :icon="$primaryIcon"
                :link="$primaryUrl"
                wire:navigate
                class="
                    btn-primary btn-sm
                    rounded-xl px-4 font-medium
                    shadow-sm shadow-primary/10
                "
            />

            <span
                aria-hidden="true"
                class="hidden select-none text-sm font-light text-base-content/15 sm:inline"
            >
                |
            </span>

            <x-theme-toggle
                class="
                    btn btn-square btn-ghost btn-sm
                    rounded-xl text-base-content/50
                    hover:bg-base-200/70 hover:text-base-content
                "
            />
        </div>
    </div>
</header>
