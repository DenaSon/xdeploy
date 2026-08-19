@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<section
    {{ $attributes->class([
        'h-full overflow-hidden rounded-2xl',
        'border border-base-300 bg-base-100',
    ]) }}
>
    {{-- Header --}}
    <header
        class="flex items-start justify-between gap-3
               border-b border-base-300
               px-4 py-3.5
               sm:gap-4 sm:px-6 sm:py-4"
    >
        <div class="flex min-w-0 items-start gap-2.5 sm:gap-3">

            @if ($icon)
                <div
                    class="flex size-8 shrink-0 items-center
                           justify-center rounded-xl
                           bg-base-200/70
                           sm:size-9"
                >
                    <x-icon
                        :name="$icon"
                        class="size-4 text-base-content/65 sm:size-4.5"
                    />
                </div>
            @endif

            <div class="min-w-0">

                <h2
                    class="truncate text-sm font-semibold
                           text-base-content sm:text-base"
                >
                    {{ $title }}
                </h2>

                @if ($subtitle)
                    <p
                        class="mt-0.5 line-clamp-2
                               text-xs leading-5
                               text-base-content/50
                               sm:text-sm"
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
    </header>

    {{-- Content --}}
    <div class="px-4 py-4 sm:px-6 sm:py-5">
        {{ $slot }}
    </div>
</section>
