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
        class="flex items-start justify-between gap-4
               border-b border-base-300
               px-5 py-4 sm:px-6"
    >
        <div class="flex min-w-0 items-start gap-3">

            @if ($icon)
                <div
                    class="flex size-9 shrink-0 items-center
                           justify-center rounded-xl
                           bg-base-200/70"
                >
                    <x-icon
                        :name="$icon"
                        class="size-4.5 text-base-content/65"
                    />
                </div>
            @endif

            <div class="min-w-0">

                <h2
                    class="truncate text-base font-semibold
                           text-base-content"
                >
                    {{ $title }}
                </h2>

                @if ($subtitle)
                    <p
                        class="mt-0.5 line-clamp-2
                               text-sm leading-5
                               text-base-content/50"
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
    <div class="px-5 py-5 sm:px-6">
        {{ $slot }}
    </div>
</section>
