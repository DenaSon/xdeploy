<div
    class="
        flex h-16
        items-center
        border-b border-base-300
        px-3
    "
>
    <a
        href="{{ route('panel.servers.index') }}"
        wire:navigate
        class="
            group
            flex min-w-0
            items-center gap-3
        "
    >
        <span
            class="
                flex size-9 shrink-0
                items-center justify-center

                rounded-xl

                bg-primary
                text-primary-content
            "
        >
            <x-icon
                name="lucide.server"
                class="size-[18px] stroke-[1.8]"
            />
        </span>

        <span
            class="
                mary-hideable
                min-w-0
            "
        >
            <span
                class="
                    block truncate
                    text-base font-semibold
                    tracking-tight
                "
            >
                xDeploy
            </span>

            <span
                class="
                    mt-0.5 block
                    text-[10px]
                    text-base-content/40
                "
            >
                by Lumixo
            </span>
        </span>

    </a>
</div>
