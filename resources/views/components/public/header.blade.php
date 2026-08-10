@props([
    'showHomeLink' => true,
])

<header
    class="
        sticky top-0 z-30

        border-b border-base-300/70
        bg-base-100/85

        backdrop-blur-xl
    "
>
    <div
        class="
            mx-auto flex h-16
            w-full max-w-7xl
            items-center justify-between

            px-4
            sm:px-6
            lg:px-8
        "
    >

        {{-- Brand --}}
        <a
            href="{{ url('/') }}"
            wire:navigate
            aria-label="xDeploy"
            class="group flex min-w-0 items-center gap-2.5"
        >
            <span
                class="
                    flex size-9 shrink-0
                    items-center justify-center

                    rounded-xl

                    bg-primary
                    text-primary-content

                    transition-transform duration-200
                    group-hover:scale-[1.03]
                "
            >
                <x-icon
                    name="lucide.server"
                    class="!size-[18px] stroke-[1.8]"
                />
            </span>

            <span class="min-w-0">

                <span
                    class="
                        block truncate

                        text-base font-semibold
                        leading-none
                        tracking-tight
                        text-base-content
                    "
                >
                    xDeploy
                </span>

                <span
                    class="
                        mt-1 block

                        text-[10px]
                        leading-none
                        text-base-content/40
                    "
                >
                    by Lumixo
                </span>

            </span>
        </a>


        {{-- Actions --}}
        <div
            class="
                flex shrink-0
                items-center gap-1.5
            "
        >

            @if($showHomeLink)
                <a
                    href="{{ url('/') }}"
                    wire:navigate
                    class="
                        btn btn-ghost btn-sm

                        hidden rounded-xl

                        px-3

                        text-xs font-normal
                        text-base-content/60

                        hover:bg-base-200
                        hover:text-base-content

                        sm:inline-flex
                    "
                >
                    صفحه اصلی
                </a>
            @endif


            {{-- Login --}}
            <x-button
                label="ورود"
                icon="lucide.log-in"
                :link="route('login')"
                wire:navigate
                class="
                    btn-primary btn-sm
                    rounded-xl

                    px-3.5
                    font-medium
                "
            />


            {{-- Theme --}}
            <x-theme-toggle
                class="
                    btn btn-square btn-ghost btn-sm
                    rounded-xl
                "
            />

        </div>

    </div>
</header>
