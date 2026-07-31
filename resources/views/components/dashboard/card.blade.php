@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<x-card
    {{ $attributes->class([
        '
            group relative h-full overflow-hidden
            rounded-3xl

            border border-base-content/8
            bg-base-100/75
            backdrop-blur-xl

            shadow-[0_8px_32px_rgba(15,23,42,0.05)]

            transition-all duration-300 ease-out

            hover:-translate-y-0.5
            hover:border-primary/15
            hover:bg-base-100/85
            hover:shadow-[0_16px_45px_rgba(15,23,42,0.08)]
        '
    ]) }}
>

    {{-- Top highlight --}}
    <div
        class="
            pointer-events-none absolute inset-x-8 top-0 h-px
            bg-gradient-to-r
            from-transparent via-base-content/15 to-transparent
        "
    ></div>

    {{-- Ambient glow --}}
    <div
        class="
            pointer-events-none absolute -top-20 -right-20
            size-48 rounded-full bg-primary/8 blur-3xl

            opacity-60 transition-opacity duration-500
            group-hover:opacity-100
        "
    ></div>

    {{-- Header --}}
    <div class="relative z-10 mb-6 flex items-start justify-between gap-4">

        <div class="flex min-w-0 items-center gap-3.5">

            @if($icon)
                <div
                    class="
                        flex size-11 shrink-0 items-center justify-center
                        rounded-2xl

                        border border-primary/10
                        bg-primary/10

                        shadow-[inset_0_1px_0_rgba(255,255,255,0.25)]

                        transition-all duration-300
                        group-hover:border-primary/15
                        group-hover:bg-primary/15
                    "
                >
                    <x-icon
                        :name="$icon"
                        class="
                            size-5 text-primary
                            transition-transform duration-300
                            group-hover:scale-105
                        "
                    />
                </div>
            @endif

            <div class="min-w-0">

                <h2
                    class="
                        truncate text-base font-semibold
                        tracking-tight text-base-content
                        sm:text-lg
                    "
                >
                    {{ $title }}
                </h2>

                @if($subtitle)
                    <p
                        class="
                            mt-1 line-clamp-2 text-xs leading-5
                            text-base-content/55 sm:text-sm
                        "
                    >
                        {{ $subtitle }}
                    </p>
                @endif

            </div>

        </div>

        @isset($menu)
            <div class="shrink-0">
                {{ $menu }}
            </div>
        @endisset

    </div>

    {{-- Content --}}
    <div class="relative z-10">
        {{ $slot }}
    </div>

</x-card>
