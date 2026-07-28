@props([
    'value',
    'label',
    'align' => 'center',
])

<div
    @class([
        '
        rounded-2xl

        border border-base-content/5
        bg-base-200/30

        p-3 lg:p-4

        transition-all duration-300 ease-out

        hover:bg-base-200/50
        hover:border-primary/20
        ',

        'text-center' => $align === 'center',
        'text-left' => $align === 'left',
        'text-right' => $align === 'right',
    ])
>

    <div class="text-sm font-semibold tracking-tight md:text-base lg:text-lg">
        {{ $value }}
    </div>

    <div class="mt-1 text-[11px] text-base-content/60 lg:text-xs">
        {{ $label }}
    </div>

</div>
