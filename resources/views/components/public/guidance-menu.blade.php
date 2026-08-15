@props([
    'documentationCategories' => [],
])

@php
    $productName = config('app.name');
@endphp

<nav
    aria-label="آموزش‌های {{ $productName }}"
    class="min-w-0"
>
    <section class="min-w-0 p-4 sm:p-5">
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
                        @click="closeGuideMenus()"
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
                @click="closeGuideMenus()"
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
