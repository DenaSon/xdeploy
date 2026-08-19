@props([
    'cpu' => [],
])

@php
    $cpu = array_replace([
        'model' => '—',
        'architecture' => '—',
        'cores' => '—',
        'threads' => '—',
    ], is_array($cpu) ? $cpu : []);

    $displayValue = static function (mixed $value): string {
        if (
            $value === null
            || $value === ''
            || ! is_scalar($value)
        ) {
            return '—';
        }

        return (string) $value;
    };

    $model = $displayValue(
        $cpu['model'],
    );

    $architecture = $displayValue(
        $cpu['architecture'],
    );

    $cores = $displayValue(
        $cpu['cores'],
    );

    $threads = $displayValue(
        $cpu['threads'],
    );

    $specifications = [
        [
            'label' => 'معماری',
            'value' => $architecture,
            'icon' => 'lucide.binary',
            'technical' => true,
        ],
        [
            'label' => 'هسته',
            'value' => $cores,
            'icon' => 'lucide.boxes',
            'technical' => true,
        ],
        [
            'label' => 'رشته',
            'value' => $threads,
            'icon' => 'lucide.workflow',
            'technical' => true,
        ],
    ];
@endphp

<section
    class="overflow-hidden rounded-2xl
           border border-base-300 bg-base-100"
>
    {{-- Header --}}
    <header
        class="flex items-start gap-2.5
               border-b border-base-300
               px-4 py-3.5
               sm:gap-3 sm:px-6 sm:py-4"
    >
        <div
            class="flex size-8 shrink-0 items-center
                   justify-center rounded-xl
                   bg-base-200/70
                   sm:size-9"
        >
            <x-icon
                name="lucide.cpu"
                class="size-4 text-base-content/65 sm:size-4.5"
            />
        </div>

        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-base-content sm:text-base">
                پردازنده
            </h2>

            <p
                class="mt-0.5 text-xs
                       text-base-content/50
                       sm:text-sm"
            >
                مشخصات پردازنده و معماری سیستم
            </p>
        </div>
    </header>

    <div
        class="grid grid-cols-1
               lg:grid-cols-12"
    >
        {{-- Processor model --}}
        <div
            class="min-w-0 border-b border-base-300
                   px-4 py-4
                   sm:px-6 sm:py-5
                   lg:col-span-7
                   lg:border-b-0
                   lg:border-l"
        >
            <div
                class="flex items-start gap-2.5 sm:gap-3"
            >
                <div
                    class="flex size-8 shrink-0 items-center
                           justify-center rounded-lg
                           bg-base-200/60
                           sm:size-9"
                >
                    <x-icon
                        name="lucide.microchip"
                        class="size-4 text-base-content/45"
                    />
                </div>

                <div class="min-w-0 flex-1">

                    <p
                        class="text-[11px] font-medium
                               text-base-content/45
                               sm:text-xs"
                    >
                        مدل پردازنده
                    </p>

                    <p
                        dir="ltr"
                        title="{{ $model }}"
                        class="technical-value mt-1.5
                               break-words text-left
                               text-sm font-medium
                               leading-6 text-base-content
                               sm:mt-2 sm:text-base"
                    >
                        {{ $model }}
                    </p>

                </div>
            </div>
        </div>

        {{-- Processor specifications --}}
        <div
            class="grid grid-cols-3
                   divide-x divide-x-reverse
                   divide-base-300
                   lg:col-span-5"
        >
            @foreach ($specifications as $item)

                <div
                    class="flex min-w-0 flex-col
                           items-center justify-center
                           px-2 py-4 text-center
                           sm:px-3 sm:py-5"
                >
                    <div
                        class="flex size-7 items-center
                               justify-center rounded-lg
                               bg-base-200/60
                               sm:size-8"
                    >
                        <x-icon
                            :name="$item['icon']"
                            class="size-3.5 text-base-content/45 sm:size-4"
                        />
                    </div>

                    <p
                        @class([
                            'mt-2.5 truncate text-sm font-semibold text-base-content sm:mt-3 sm:text-base',
                            'technical-value' => $item['technical'],
                        ])
                        @if ($item['technical'])
                            dir="ltr"
                            title="{{ $item['value'] }}"
                        @endif
                    >
                        {{ $item['value'] }}
                    </p>

                    <p
                        class="mt-1 text-[11px]
                               text-base-content/45
                               sm:text-xs"
                    >
                        {{ $item['label'] }}
                    </p>
                </div>

            @endforeach
        </div>
    </div>
</section>
