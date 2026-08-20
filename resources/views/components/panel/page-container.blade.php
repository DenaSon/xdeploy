@props([
    'wide' => false,
])

<main
    {{ $attributes->class([
        'min-w-0 flex-1',
        'px-0 py-5',
        'sm:px-5',
        'lg:px-8 lg:py-7',
    ]) }}
>
    <div
        @class([
            'mx-auto w-full',
            'max-w-[1600px]' => ! $wide,
        ])
    >
        {{ $slot }}
    </div>
</main>
