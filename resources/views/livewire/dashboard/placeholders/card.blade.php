@props([
    'variant' => 'default',
])

@php
    $variant = in_array(
        $variant,
        [
            'overview',
            'services',
            'docker',
            'cpu',
            'resources',
            'default',
        ],
        true,
    )
        ? $variant
        : 'default';
@endphp

<div
    class="
        relative isolate w-full min-w-0 overflow-hidden
        rounded-3xl
        border border-base-content/8
        bg-base-100/75
        p-5
        shadow-[0_8px_32px_rgba(15,23,42,0.05)]
        backdrop-blur-xl
        sm:p-6
    "
    aria-hidden="true"
>
    {{-- Soft ambient glow --}}
    <div
        class="
            pointer-events-none absolute -top-20 -right-20 -z-10
            size-40 rounded-full
            bg-primary/5 blur-3xl
        "
    ></div>

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3.5">

            {{-- Icon --}}
            <div
                class="
                    skeleton
                    size-11 shrink-0
                    rounded-2xl
                "
            ></div>

            <div class="min-w-0 space-y-2">

                {{-- Title --}}
                <div
                    class="
                        skeleton
                        h-4 w-32
                        rounded-lg
                    "
                ></div>

                {{-- Subtitle --}}
                <div
                    class="
                        skeleton
                        h-3 w-20
                        rounded-lg
                        opacity-60
                    "
                ></div>
            </div>
        </div>

        {{-- Header action / status --}}
        <div
            class="
                skeleton
                h-6 w-14
                rounded-full
                opacity-60
            "
        ></div>
    </div>


    {{-- ========================================================= --}}
    {{-- SERVER OVERVIEW --}}
    {{-- ========================================================= --}}

    @if ($variant === 'overview')

        <div
            class="
                mt-6 grid grid-cols-1
                gap-3
                sm:grid-cols-2
                sm:gap-4
            "
        >
            @for ($index = 0; $index < 6; $index++)
                <div
                    class="
                        rounded-2xl
                        border border-base-content/5
                        bg-base-200/25
                        p-4
                    "
                >
                    <div
                        class="
                            skeleton
                            h-3 w-20
                            rounded-md
                            opacity-60
                        "
                    ></div>

                    <div
                        class="
                            skeleton
                            mt-3 h-5
                            rounded-lg
                        "
                        @style([
                            'width: 65%' => $index % 3 === 0,
                            'width: 78%' => $index % 3 === 1,
                            'width: 52%' => $index % 3 === 2,
                        ])
                    ></div>
                </div>
            @endfor
        </div>


        {{-- ========================================================= --}}
        {{-- SYSTEM SERVICES --}}
        {{-- ========================================================= --}}

    @elseif ($variant === 'services')

        {{-- Summary badges --}}
        <div class="mt-6 flex items-center gap-2">

            <div
                class="
                    skeleton
                    h-6 w-16
                    rounded-full
                "
            ></div>

            <div
                class="
                    skeleton
                    h-6 w-20
                    rounded-full
                    opacity-70
                "
            ></div>
        </div>

        {{-- Service rows --}}
        <div class="mt-5 space-y-3">

            @for ($index = 0; $index < 6; $index++)

                <div
                    class="
                        flex items-center justify-between
                        gap-4
                        rounded-2xl
                        border border-base-content/5
                        bg-base-200/20
                        px-4 py-3.5
                    "
                >
                    <div
                        class="
                            flex min-w-0
                            flex-1 items-center
                            gap-3
                        "
                    >
                        <div
                            class="
                                skeleton
                                size-2.5 shrink-0
                                rounded-full
                            "
                        ></div>

                        <div class="min-w-0 flex-1">

                            <div
                                class="
                                    skeleton
                                    h-3.5 rounded-md
                                "
                                @style([
                                    'width: 42%' => $index % 2 === 0,
                                    'width: 58%' => $index % 2 !== 0,
                                ])
                            ></div>

                            <div
                                class="
                                    skeleton
                                    mt-2 h-2.5 w-1/3
                                    rounded-md
                                    opacity-50
                                "
                            ></div>
                        </div>
                    </div>

                    <div
                        class="
                            skeleton
                            h-6 w-14 shrink-0
                            rounded-full
                            opacity-70
                        "
                    ></div>
                </div>

            @endfor

        </div>


        {{-- ========================================================= --}}
        {{-- DOCKER --}}
        {{-- ========================================================= --}}

    @elseif ($variant === 'docker')

        <div class="mt-6 space-y-3">

            @for ($index = 0; $index < 3; $index++)

                <div
                    class="
                        rounded-2xl
                        border border-base-content/5
                        bg-base-200/20
                        p-4
                    "
                >
                    <div
                        class="
                            flex items-center
                            justify-between gap-4
                        "
                    >
                        <div
                            class="
                                flex min-w-0
                                items-center gap-3
                            "
                        >
                            <div
                                class="
                                    skeleton
                                    size-9 shrink-0
                                    rounded-xl
                                "
                            ></div>

                            <div class="min-w-0">
                                <div
                                    class="
                                        skeleton
                                        h-3.5 w-28
                                        rounded-md
                                    "
                                ></div>

                                <div
                                    class="
                                        skeleton
                                        mt-2 h-2.5 w-20
                                        rounded-md
                                        opacity-50
                                    "
                                ></div>
                            </div>
                        </div>

                        <div
                            class="
                                skeleton
                                h-6 w-16
                                rounded-full
                            "
                        ></div>
                    </div>

                    <div
                        class="
                            mt-4 flex
                            items-center gap-2
                        "
                    >
                        <div
                            class="
                                skeleton
                                h-5 w-24
                                rounded-lg
                                opacity-60
                            "
                        ></div>

                        <div
                            class="
                                skeleton
                                h-5 w-20
                                rounded-lg
                                opacity-50
                            "
                        ></div>
                    </div>
                </div>

            @endfor

        </div>


        {{-- ========================================================= --}}
        {{-- CPU --}}
        {{-- ========================================================= --}}

    @elseif ($variant === 'cpu')

        <div
            class="
                mt-6 grid
                grid-cols-2
                gap-3
                md:grid-cols-4
            "
        >
            @for ($index = 0; $index < 4; $index++)

                <div
                    class="
                        rounded-2xl
                        border border-base-content/5
                        bg-base-200/20
                        p-4
                    "
                >
                    <div
                        class="
                            skeleton
                            h-3 w-16
                            rounded-md
                            opacity-60
                        "
                    ></div>

                    <div
                        class="
                            skeleton
                            mt-4 h-6
                            rounded-lg
                        "
                        @style([
                            'width: 55%' => $index % 2 === 0,
                            'width: 72%' => $index % 2 !== 0,
                        ])
                    ></div>
                </div>

            @endfor
        </div>


        {{-- ========================================================= --}}
        {{-- RESOURCE USAGE --}}
        {{-- ========================================================= --}}

    @elseif ($variant === 'resources')

        <div
            class="
                mt-6 grid
                grid-cols-1
                gap-4
                md:grid-cols-3
            "
        >

            @for ($index = 0; $index < 3; $index++)

                <div
                    class="
                        rounded-2xl
                        border border-base-content/5
                        bg-base-200/20
                        p-4
                    "
                >
                    <div
                        class="
                            flex items-center
                            justify-between gap-4
                        "
                    >
                        <div
                            class="
                                skeleton
                                h-3.5 w-20
                                rounded-md
                            "
                        ></div>

                        <div
                            class="
                                skeleton
                                h-5 w-12
                                rounded-md
                                opacity-60
                            "
                        ></div>
                    </div>

                    {{-- Progress bar --}}
                    <div
                        class="
                            mt-5 h-2
                            overflow-hidden
                            rounded-full
                            bg-base-200
                        "
                    >
                        <div
                            class="
                                skeleton
                                h-full rounded-full
                            "
                            @style([
                                'width: 62%' => $index === 0,
                                'width: 43%' => $index === 1,
                                'width: 76%' => $index === 2,
                            ])
                        ></div>
                    </div>

                    <div class="mt-4 flex gap-2">

                        <div
                            class="
                                skeleton
                                h-3 w-16
                                rounded-md
                                opacity-50
                            "
                        ></div>

                        <div
                            class="
                                skeleton
                                h-3 w-12
                                rounded-md
                                opacity-40
                            "
                        ></div>

                    </div>
                </div>

            @endfor

        </div>


        {{-- ========================================================= --}}
        {{-- DEFAULT --}}
        {{-- ========================================================= --}}

    @else

        <div class="mt-6 space-y-3">

            @for ($index = 0; $index < 4; $index++)
                <div
                    class="
                        skeleton
                        h-14
                        rounded-2xl
                    "
                ></div>
            @endfor

        </div>

    @endif


    {{-- Bottom subtle loading status --}}
    <div
        class="
            mt-5 flex items-center
            justify-end gap-2
            text-[11px]
            text-base-content/30
        "
    >
        <span
            class="
                loading
                loading-dots
                loading-xs
            "
        ></span>

        <span>
            در حال دریافت اطلاعات
        </span>
    </div>
</div>
