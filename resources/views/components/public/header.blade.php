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

    $guideMenuId = 'public-guidance-megamenu';
@endphp

<header
    x-data="{
        scrolled: {{ $isLanding ? 'window.scrollY > 12' : 'true' }},

        closeGuideMenu() {
            document.getElementById('{{ $guideMenuId }}')?.hidePopover()
        },

        scrollToSection(id) {
            this.closeGuideMenu()

            document.querySelector(`#${id}`)?.scrollIntoView({
                behavior: window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches ? 'auto' : 'smooth',
                block: 'start',
            })
        },
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

        {{-- Guidance trigger --}}
        @if($showPublicNavigation)
            <div class="ms-6 hidden items-center lg:flex">
                <span
                    aria-hidden="true"
                    class="me-5 select-none text-sm font-light text-base-content/15"
                >
                    |
                </span>

                <button
                    type="button"
                    popovertarget="{{ $guideMenuId }}"
                    aria-label="بازکردن راهنما"
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
                        class="!size-3.5 stroke-[1.8] text-base-content/35"
                    />
                </button>
            </div>
        @endif

        <div class="flex-1"></div>

        {{-- Actions --}}
        <div class="flex shrink-0 items-center gap-2">
            @if($showPublicNavigation)
                <button
                    type="button"
                    popovertarget="{{ $guideMenuId }}"
                    aria-label="بازکردن راهنما"
                    class="
                        btn btn-square btn-ghost btn-sm
                        rounded-xl text-base-content/50
                        hover:bg-base-200/70 hover:text-base-content
                        lg:hidden
                    "
                >
                    <x-icon
                        name="lucide.book-open-text"
                        class="!size-4 stroke-[1.8]"
                    />
                </button>
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

    {{-- Guidance mega menu --}}
    @if($showPublicNavigation)
        <div
            id="{{ $guideMenuId }}"
            popover
            class="
                megamenu megamenu-wide max-lg:megamenu-vertical
                w-[min(46rem,calc(100vw-2rem))]
                overflow-hidden rounded-2xl
                border border-base-300/80
                bg-base-100/95 p-0
                shadow-xl shadow-base-content/[0.08]
                backdrop-blur-xl
            "
        >
            <span class="megamenu-active"></span>

            <nav
                aria-label="راهنمای {{ $productName }}"
                class="grid min-w-0 lg:grid-cols-[15rem_minmax(0,1fr)]"
            >
                {{-- Product links --}}
                <section
                    class="
                        border-b border-base-300/70 bg-base-200/35 p-4
                        lg:border-b-0 lg:border-e lg:p-5
                    "
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-primary/10 text-primary
                            "
                        >
                            <x-icon
                                name="lucide.server-cog"
                                class="!size-4 stroke-[1.8]"
                            />
                        </span>

                        <div>
                            <div class="text-[10px] font-medium text-base-content/35">
                                درباره محصول
                            </div>

                            <div
                                dir="ltr"
                                class="mt-0.5 text-xs font-semibold text-base-content/75"
                            >
                                {{ $productName }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1.5">
                        <a
                            href="{{ route('home') }}#how-it-works"
                            @if($isLanding)
                                @click.prevent="scrollToSection('how-it-works')"
                            @else
                                @click="closeGuideMenu()"
                            @endif
                            class="
                                group flex items-start gap-3 rounded-xl px-3 py-2.5
                                transition-colors duration-150 hover:bg-base-100
                            "
                        >
                            <span
                                class="
                                    mt-0.5 flex size-7 shrink-0 items-center justify-center
                                    rounded-lg bg-base-100 text-base-content/40
                                    ring-1 ring-base-300/60
                                    transition-colors group-hover:text-primary
                                "
                            >
                                <x-icon
                                    name="lucide.workflow"
                                    class="!size-3.5 stroke-[1.8]"
                                />
                            </span>

                            <span class="min-w-0">
                                <span
                                    class="
                                        block text-xs font-semibold text-base-content/70
                                        transition-colors group-hover:text-primary
                                    "
                                >
                                    نحوه عملکرد
                                </span>

                                <span class="mt-0.5 block text-[10px] leading-5 text-base-content/40">
                                    از سرور تا اجرای سرویس
                                </span>
                            </span>
                        </a>

                        <a
                            href="{{ route('home') }}#capabilities"
                            @if($isLanding)
                                @click.prevent="scrollToSection('capabilities')"
                            @else
                                @click="closeGuideMenu()"
                            @endif
                            class="
                                group flex items-start gap-3 rounded-xl px-3 py-2.5
                                transition-colors duration-150 hover:bg-base-100
                            "
                        >
                            <span
                                class="
                                    mt-0.5 flex size-7 shrink-0 items-center justify-center
                                    rounded-lg bg-base-100 text-base-content/40
                                    ring-1 ring-base-300/60
                                    transition-colors group-hover:text-primary
                                "
                            >
                                <x-icon
                                    name="lucide.blocks"
                                    class="!size-3.5 stroke-[1.8]"
                                />
                            </span>

                            <span class="min-w-0">
                                <span
                                    class="
                                        block text-xs font-semibold text-base-content/70
                                        transition-colors group-hover:text-primary
                                    "
                                >
                                    قابلیت‌ها
                                </span>

                                <span class="mt-0.5 block text-[10px] leading-5 text-base-content/40">
                                    امکانات اصلی {{ $productName }}
                                </span>
                            </span>
                        </a>
                    </div>
                </section>

                {{-- Documentation categories --}}
                <section class="min-w-0 p-4 lg:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="
                                    flex size-8 shrink-0 items-center justify-center
                                    rounded-lg bg-primary/10 text-primary
                                "
                            >
                                <x-icon
                                    name="lucide.graduation-cap"
                                    class="!size-4 stroke-[1.8]"
                                />
                            </span>

                            <div>
                                <h2 class="text-sm font-semibold text-base-content/80">
                                    آموزش‌ها
                                </h2>

                                <p class="mt-0.5 text-[10px] text-base-content/40">
                                    راهنمای استفاده از {{ $productName }}
                                </p>
                            </div>
                        </div>

                        <span class="hidden text-[10px] text-base-content/30 sm:inline">
                            {{ count($documentationCategories) }} دسته
                        </span>
                    </div>

                    @if($documentationCategories !== [])
                        <div
                            class="
                                dashboard-scroll mt-4 grid max-h-64 gap-1.5
                                overflow-y-auto pe-1 sm:grid-cols-2
                            "
                        >
                            @foreach($documentationCategories as $category)
                                <a
                                    href="{{ route('docs.index') }}#docs-category-{{ $category['slug'] }}"
                                    @click="closeGuideMenu()"
                                    class="
                                        group flex min-w-0 items-start gap-2.5
                                        rounded-xl px-3 py-2.5
                                        transition-colors duration-150
                                        hover:bg-base-200/55
                                    "
                                >
                                    <span
                                        class="
                                            mt-0.5 flex size-7 shrink-0 items-center justify-center
                                            rounded-lg bg-base-200/70 text-base-content/35
                                            transition-colors
                                            group-hover:bg-primary/10 group-hover:text-primary
                                        "
                                    >
                                        <x-icon
                                            name="lucide.folder-open"
                                            class="!size-3.5 stroke-[1.8]"
                                        />
                                    </span>

                                    <span class="min-w-0">
                                        <span
                                            class="
                                                block truncate text-xs font-medium text-base-content/65
                                                transition-colors group-hover:text-primary
                                            "
                                        >
                                            {{ $category['title'] }}
                                        </span>

                                        @if($category['description'])
                                            <span
                                                class="
                                                    mt-0.5 block line-clamp-1
                                                    text-[10px] leading-5 text-base-content/35
                                                "
                                            >
                                                {{ $category['description'] }}
                                            </span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div
                            class="
                                mt-4 flex items-center gap-3 rounded-xl
                                bg-base-200/45 px-3.5 py-3
                            "
                        >
                            <x-icon
                                name="lucide.book-dashed"
                                class="!size-4 shrink-0 text-base-content/30"
                            />

                            <p class="text-[11px] leading-5 text-base-content/40">
                                هنوز دسته آموزشی منتشرشده‌ای وجود ندارد.
                            </p>
                        </div>
                    @endif

                    <div class="mt-4 border-t border-base-300/60 pt-3">
                        <a
                            href="{{ route('docs.index') }}"
                            wire:navigate
                            @click="closeGuideMenu()"
                            class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary"
                        >
                            مشاهده همه آموزش‌ها

                            <x-icon
                                name="lucide.arrow-left"
                                class="
                                    !size-3.5 stroke-[1.8]
                                    transition-transform group-hover:-translate-x-0.5
                                "
                            />
                        </a>
                    </div>
                </section>
            </nav>
        </div>
    @endif
</header>
