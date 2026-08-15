@php
    $productName = config('app.name');
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
            flex w-full max-w-7xl
            flex-col
            items-center justify-between
            gap-4

            px-4 py-6

            sm:flex-row
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
                flex items-center gap-2.5
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


        {{-- Public links --}}
        <nav
            aria-label="پیوندهای عمومی"
            class="
                flex items-center gap-3
                text-xs text-base-content/45
            "
        >
            <a
                href="{{ route('docs.index') }}"
                wire:navigate
                class="transition-colors hover:text-primary"
            >
                مستندات
            </a>
        </nav>


        {{-- Copyright --}}
        <div
            class="
                flex flex-wrap
                items-center justify-center
                gap-x-1.5 gap-y-1

                text-center
                text-[11px]
                text-base-content/40

                sm:justify-end
                sm:text-start
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
                class="
                    hidden
                    text-base-content/20

                    sm:inline
                "
            >
                ·
            </span>

            <span>
                محصولی از Lumixo
            </span>
        </div>
    </div>
</footer>
