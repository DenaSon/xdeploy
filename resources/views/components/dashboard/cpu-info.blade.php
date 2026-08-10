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
        class="flex items-start gap-3
               border-b border-base-300
               px-5 py-4 sm:px-6"
    >
        <div
            class="flex size-9 shrink-0 items-center
                   justify-center rounded-xl
                   bg-base-200/70"
        >
            <x-icon
                name="lucide.cpu"
                class="size-4.5 text-base-content/65"
            />
        </div>

        <div>
            <h2 class="font-semibold text-base-content">
                پردازنده
            </h2>

            <p
                class="mt-0.5 text-sm
                       text-base-content/50"
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
                   px-5 py-5
                   sm:px-6
                   lg:col-span-7
                   lg:border-b-0
                   lg:border-l"
        >
            <div
                class="flex items-start gap-3"
            >
                <div
                    class="flex size-9 shrink-0 items-center
                           justify-center rounded-lg
                           bg-base-200/60"
                >
                    <x-icon
                        name="lucide.microchip"
                        class="size-4 text-base-content/45"
                    />
                </div>

                <div class="min-w-0 flex-1">

                    <p
                        class="text-xs font-medium
                               text-base-content/45"
                    >
                        مدل پردازنده
                    </p>

                    <p
                        dir="ltr"
                        class="technical-value mt-2
                               break-words text-left
                               text-sm font-medium
                               leading-6 text-base-content
                               sm:text-base"
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
                           px-3 py-5 text-center"
                >
                    <div
                        class="flex size-8 items-center
                               justify-center rounded-lg
                               bg-base-200/60"
                    >
                        <x-icon
                            :name="$item['icon']"
                            class="size-4 text-base-content/45"
                        />
                    </div>

                    <p
                        @class([
                            'mt-3 truncate text-sm font-semibold text-base-content sm:text-base',
                            'technical-value' => $item['technical'],
                        ])
                        @if ($item['technical'])
                            dir="ltr"
                        @endif
                    >
                        {{ $item['value'] }}
                    </p>

                    <p
                        class="mt-1 text-xs
                               text-base-content/45"
                    >
                        {{ $item['label'] }}
                    </p>
                </div>

            @endforeach
        </div>
    </div>
</section>
