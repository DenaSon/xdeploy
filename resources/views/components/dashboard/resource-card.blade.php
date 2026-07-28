@props([
    'title',
    'subtitle' => null,
    'icon',
    'color' => 'primary',
    'percent' => 0,

    'left',
    'leftLabel',

    'right',
    'rightLabel',

    'footer' => null,
])

<x-dashboard.card
    :title="$title"
    :subtitle="$subtitle"
    :icon="$icon"
>

    <div class="flex justify-end">

        <div class="text-right">

            <div class="text-3xl font-semibold tracking-tight lg:text-4xl">
                {{ $percent }}%
            </div>

            <div class="text-xs text-base-content/60">
                مصرف
            </div>

        </div>

    </div>

    <progress
        class="progress progress-{{ $color }} mt-4 h-1.5 w-full lg:mt-5"
        value="{{ $percent }}"
        max="100">
    </progress>

    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:mt-6">

        <x-dashboard.stat
            :value="$left"
            :label="$leftLabel"
            align="right"
        />

        <x-dashboard.stat
            :value="$right"
            :label="$rightLabel"
            align="left"
        />

    </div>

    @if($footer)

        <div class="mt-4 border-t border-base-content/5 pt-4 text-xs text-base-content/60 lg:mt-5 lg:text-sm">
            {{ $footer }}
        </div>

    @endif

</x-dashboard.card>
