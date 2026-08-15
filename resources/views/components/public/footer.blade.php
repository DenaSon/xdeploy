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
            gap-6

            px-4 py-7

            sm:px-6

            lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)]
            lg:items-center
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

                    transition-colors
                    duration-200

                    group-hover:bg-primary/15
                "
            >
                <x-icon
                    name="lucide.server"
                    class="!size-4 stroke-[1.8]"
                />
            </span>

            <span
                dir="ltr"
                class="
                    text-sm
                    font-semibold
                    tracking-tight
                    text-base-content/75

                    transition-colors
                    duration-200

                    group-hover:text-base-content
                "
            >
                {{ $productName }}
            </span>
        </a>


        {{-- Public navigation --}}
        <nav
            aria-label="پیوندهای عمومی"
            class="
                flex flex-wrap
                items-center gap-x-4 gap-y-2

                text-xs
                text-base-content/45

                lg:justify-center
            "
        >
            <a
                href="{{ route('docs.index') }}"
                wire:navigate
                class="transition-colors hover:text-primary"
            >
                مستندات
            </a>

            @foreach($footerPages as $page)
                <a
                    href="{{ route('pages.show', $page['slug']) }}"
                    wire:navigate
                    class="transition-colors hover:text-primary"
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

                text-[11px]
                text-base-content/40

                lg:justify-end
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
