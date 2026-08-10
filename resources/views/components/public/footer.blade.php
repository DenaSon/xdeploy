<footer
    class="
        border-t border-base-300/70
        bg-base-100
    "
>
    <div
        class="
            mx-auto flex w-full max-w-7xl
            flex-col items-center justify-between
            gap-3
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
            aria-label="xDeploy"
            class="
                group flex
                items-center gap-2
            "
        >
            <span
                class="
                    flex size-7 shrink-0
                    items-center justify-center

                    rounded-lg
                    bg-primary/10
                    text-primary
                "
            >
                <x-icon
                    name="lucide.server"
                    class="!size-3.5 stroke-[1.8]"
                />
            </span>

            <span
                dir="ltr"
                class="
                    text-sm font-semibold
                    tracking-tight
                    text-base-content/75

                    transition-colors duration-200
                    group-hover:text-base-content
                "
            >
                xDeploy
            </span>
        </a>


        {{-- Copyright --}}
        <div
            class="
                text-center
                text-[11px]
                text-base-content/40

                sm:text-start
            "
        >
            <span>
                © {{ now()->year }}
            </span>

            <span
                dir="ltr"
                class="ms-1"
            >
                xDeploy by Lumixo
            </span>
        </div>

    </div>
</footer>
