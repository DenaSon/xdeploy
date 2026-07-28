@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<x-card
    class="
        relative overflow-hidden

        border border-base-content/5
        bg-base-100/70
        backdrop-blur-sm

        shadow-[0_8px_30px_rgba(15,23,42,0.06)]

        transition-all duration-300 ease-out

        hover:bg-base-100/80
        hover:border-primary/20
        hover:shadow-[0_12px_40px_rgba(15,23,42,0.10)]
    "
>

    {{-- Top Highlight --}}
    <div
        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/40"
    ></div>

    <div class="mb-5 flex items-center justify-between">

        <div class="flex items-center gap-4">

            @if($icon)
                <div
                    class="
                        flex size-11 items-center justify-center
                        rounded-2xl

                        border border-base-content/5
                        bg-primary/10

                        shadow-inner
                        transition-colors duration-300

                        group-hover:bg-primary/15
                    "
                >
                    <x-icon
                        :name="$icon"
                        class="size-5 text-primary/90"
                    />
                </div>
            @endif

            <div>

                <h2 class="text-lg font-semibold tracking-tight">
                    {{ $title }}
                </h2>

                @if($subtitle)
                    <p class="mt-0.5 text-sm text-base-content/60">
                        {{ $subtitle }}
                    </p>
                @endif

            </div>

        </div>

        @isset($menu)
            {{ $menu }}
        @endisset

    </div>

    {{ $slot }}

</x-card>
