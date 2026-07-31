@props([
    'value',
    'label',
    'align' => 'center',
])

<div
    {{ $attributes->class([
        '
            group relative flex min-h-24 min-w-0
            flex-col justify-center overflow-hidden

            rounded-2xl
            border border-base-content/8
            bg-gradient-to-br
            from-base-200/50 to-base-200/20

            px-4 py-3.5

            transition-all duration-300 ease-out

            hover:-translate-y-px
            hover:border-primary/15
            hover:from-base-200/65
            hover:to-base-200/30
            hover:shadow-[0_8px_24px_rgba(15,23,42,0.05)]
        ',

        'text-center' => $align === 'center',
        'text-left'   => $align === 'left',
        'text-right'  => $align === 'right',
    ]) }}
>

    {{-- Top highlight --}}
    <div
        class="
            pointer-events-none absolute inset-x-5 top-0 h-px
            bg-gradient-to-r
            from-transparent via-base-content/10 to-transparent
        "
    ></div>

    {{-- Value --}}
    <div
        class="
            relative min-w-0 break-words
            text-sm font-semibold leading-6
            tracking-tight text-base-content
            tabular-nums

            transition-colors duration-300
            group-hover:text-primary

            sm:text-base
        "
    >
        {{ filled($value) ? $value : '—' }}
    </div>

    {{-- Label --}}
    <div
        class="
            relative mt-1
            text-[11px] leading-5
            text-base-content/55
            sm:text-xs
        "
    >
        {{ $label }}
    </div>

</div>
