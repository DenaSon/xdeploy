<div
    class="
        border-b
        border-base-300
        px-5
        py-5
    "
>

    <a
        href="{{ route('panel.dashboard') }}"
        wire:navigate
        class="flex items-center gap-3"
    >

        <div
            class="
                flex
                size-11
                items-center
                justify-center
                rounded-2xl
                bg-primary
                text-primary-content
                shadow-sm
            "
        >

            <x-icon
                name="lucide.rocket"
                class="size-5"
            />

        </div>

        <div>

            <h2 class="text-lg font-black leading-none">
                xDeploy
            </h2>

            <p class="mt-1 text-xs text-base-content/60">
                Deploy & Manage VPS
            </p>

        </div>

    </a>

</div>
