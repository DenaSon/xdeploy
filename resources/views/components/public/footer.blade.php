@php
    $productName = config('app.name');
    $footerPages = app(App\Application\Navigation\PublicFooterNavigation::class)->pages();
@endphp

<footer
    class="
        border-t border-base-300/70
        bg-base-100
    "
>
    <div
        class="
            mx-auto
            grid w-full max-w-7xl
            gap-5

            px-4 pt-6
            pb-[calc(1.5rem+env(safe-area-inset-bottom))]

            sm:gap-6
            sm:px-6 sm:pt-8
            sm:pb-[calc(2rem+env(safe-area-inset-bottom))]

            lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)]
            lg:items-center
            lg:px-8
        "
    >
        {{-- Brand --}}
        <div class="min-w-0 space-y-2">
            <a
                href="{{ url('/') }}"
                wire:navigate
                aria-label="{{ $productName }}"
                class="
                    group
                    flex w-fit items-center gap-2.5
                "
            >
                <span
                    class="
                        flex size-8 shrink-0
                        items-center justify-center

                        rounded-lg
                        bg-primary/10
                        text-primary

                        ring-1 ring-primary/10

                        transition-colors
                        duration-200

                        group-hover:bg-primary/15
                    "
                >
                    <x-icon
                        name="lucide.server-cog"
                        class="!size-4 stroke-[1.8]"
                    />
                </span>

                <span
                    dir="ltr"
                    class="
                        text-sm
                        font-semibold
                        tracking-tight
                        text-base-content/80

                        transition-colors
                        duration-200

                        group-hover:text-base-content
                    "
                >
                    {{ $productName }}
                </span>
            </a>

            <p
                class="
                    max-w-xs
                    text-[11px] leading-5
                    text-base-content/40

                    sm:text-xs
                "
            >
                از سرور تا سرویس، در یک محیط یکپارچه.
            </p>
        </div>


        {{-- Public navigation --}}
        <nav
            aria-label="پیوندهای عمومی"
            class="
                grid grid-cols-2
                gap-1

                text-xs
                text-base-content/50

                sm:flex sm:flex-wrap
                sm:items-center sm:gap-x-1 sm:gap-y-1

                lg:justify-center
            "
        >
            <a
                href="{{ route('docs.index') }}"
                wire:navigate
                class="
                    inline-flex min-h-10
                    items-center
                    rounded-xl
                    px-2.5

                    transition-colors
                    duration-150

                    hover:bg-base-200/60
                    hover:text-primary

                    sm:min-h-9
                    sm:px-3
                "
            >
                مستندات
            </a>

            @foreach($footerPages as $page)
                <a
                    href="{{ route('pages.show', $page['slug']) }}"
                    wire:navigate
                    class="
                        inline-flex min-h-10
                        items-center
                        rounded-xl
                        px-2.5

                        transition-colors
                        duration-150

                        hover:bg-base-200/60
                        hover:text-primary

                        sm:min-h-9
                        sm:px-3
                    "
                >
                    {{ $page['title'] }}
                </a>
            @endforeach
        </nav>


        {{-- Copyright --}}
        <div
            class="
                flex flex-wrap
                items-center
                gap-x-1.5 gap-y-1

                border-t border-base-300/60
                pt-4

                text-xs
                text-base-content/40

                lg:justify-end
                lg:border-t-0
                lg:pt-0
                lg:text-[11px]
                lg:text-start
            "
        >
            <span dir="ltr">
                © {{ now()->year }}
            </span>

            <span dir="ltr">
                {{ $productName }}
            </span>

            <span
                aria-hidden="true"
                class="text-base-content/20"
            >
                ·
            </span>

            <span>
                محصولی از Lumixo
            </span>
        </div>
    </div>
</footer>
