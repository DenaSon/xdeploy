@props([
    'memory' => [],
    'disk' => [],
    'loadAverage' => [],
])

@php
    $normalizeResource = static function (mixed $resource): array {
        $defaults = [
            'usagePercent' => 0,
            'used' => '—',
            'available' => '—',
            'total' => '—',
        ];

        if (! is_array($resource)) {
            return $defaults;
        }

        $resource = array_replace(
            $defaults,
            $resource,
        );

        $percent = is_numeric($resource['usagePercent'])
            ? (float) $resource['usagePercent']
            : 0;

        $resource['usagePercent'] = max(
            0,
            min(100, $percent),
        );

        foreach (['used', 'available', 'total'] as $key) {
            if (
                $resource[$key] === null
                || $resource[$key] === ''
                || ! is_scalar($resource[$key])
            ) {
                $resource[$key] = '—';
            }
        }

        return $resource;
    };

    $normalizeLoadAverage = static function (mixed $loadAverage): array {
        $defaults = [
            'oneMinute' => '—',
            'fiveMinutes' => '—',
            'fifteenMinutes' => '—',
        ];

        if (! is_array($loadAverage)) {
            return $defaults;
        }

        $loadAverage = array_replace(
            $defaults,
            $loadAverage,
        );

        foreach ($loadAverage as $key => $value) {
            if (
                $value === null
                || $value === ''
                || ! is_scalar($value)
            ) {
                $loadAverage[$key] = '—';
            }
        }

        return $loadAverage;
    };

    $usageClasses = static function (float $percent): array {
        return match (true) {
            $percent >= 90 => [
                'bar' => 'bg-error',
                'text' => 'text-error',
            ],

            $percent >= 75 => [
                'bar' => 'bg-warning',
                'text' => 'text-warning',
            ],

            default => [
                'bar' => 'bg-primary',
                'text' => 'text-primary',
            ],
        };
    };

    $memory = $normalizeResource(
        $memory,
    );

    $disk = $normalizeResource(
        $disk,
    );

    $loadAverage = $normalizeLoadAverage(
        $loadAverage,
    );

    $resources = [
        [
            'title' => 'حافظه',
            'description' => 'مصرف حافظه RAM',
            'icon' => 'lucide.memory-stick',
            'percent' => $memory['usagePercent'],
            'used' => $memory['used'],
            'available' => $memory['available'],
            'total' => $memory['total'],
            'availableLabel' => 'در دسترس',
        ],

        [
            'title' => 'فضای دیسک',
            'description' => 'پارتیشن اصلی سیستم',
            'icon' => 'lucide.hard-drive',
            'percent' => $disk['usagePercent'],
            'used' => $disk['used'],
            'available' => $disk['available'],
            'total' => $disk['total'],
            'availableLabel' => 'باقی‌مانده',
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
                name="lucide.gauge"
                class="size-4 text-base-content/65 sm:size-4.5"
            />
        </div>

        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-base-content sm:text-base">
                مصرف منابع
            </h2>

            <p
                class="mt-0.5 text-xs
                       text-base-content/50
                       sm:text-sm"
            >
                وضعیت حافظه، فضای دیسک و بار سیستم
            </p>
        </div>
    </header>

    {{-- Memory & Disk --}}
    <div
        class="grid grid-cols-1
               lg:grid-cols-2"
    >
        @foreach ($resources as $index => $resource)

            @php
                $classes = $usageClasses(
                    $resource['percent'],
                );
            @endphp

            <div
                @class([
                    'min-w-0 px-4 py-4 sm:px-6 sm:py-5',
                    'border-b border-base-300 lg:border-b-0 lg:border-l'
                        => $index === 0,
                ])
            >
                {{-- Resource title --}}
                <div
                    class="flex items-start justify-between gap-3 sm:gap-4"
                >
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">

                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg bg-base-200/60
                                   sm:size-9"
                        >
                            <x-icon
                                :name="$resource['icon']"
                                class="size-4 text-base-content/45"
                            />
                        </div>

                        <div class="min-w-0">

                            <h3
                                class="text-sm font-semibold
                                       text-base-content"
                            >
                                {{ $resource['title'] }}
                            </h3>

                            <p
                                class="mt-0.5 text-[11px]
                                       text-base-content/45
                                       sm:text-xs"
                            >
                                {{ $resource['description'] }}
                            </p>

                        </div>
                    </div>

                    <span
                        class="technical-value shrink-0
                               text-sm font-semibold
                               {{ $classes['text'] }}"
                        dir="ltr"
                    >
                        {{ round($resource['percent']) }}%
                    </span>
                </div>

                {{-- Usage bar --}}
                <div class="mt-4 sm:mt-5">

                    <div
                        class="h-2 overflow-hidden rounded-full
                               bg-base-200"
                        role="progressbar"
                        aria-valuenow="{{ $resource['percent'] }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full
                                   transition-[width] duration-500
                                   {{ $classes['bar'] }}"
                            style="width: {{ $resource['percent'] }}%"
                        ></div>
                    </div>

                </div>

                {{-- Resource values --}}
                <div
                    class="mt-4 grid grid-cols-3
                           divide-x divide-x-reverse
                           divide-base-300
                           sm:mt-5"
                >
                    <div class="min-w-0 px-1.5 text-center first:pr-0 sm:px-3">
                        <p
                            class="text-[10px] leading-5
                                   text-base-content/45
                                   sm:text-xs"
                        >
                            استفاده‌شده
                        </p>

                        <p
                            dir="ltr"
                            title="{{ $resource['used'] }}"
                            class="technical-value mt-1.5
                                   truncate text-xs font-medium
                                   text-base-content
                                   sm:text-sm"
                        >
                            {{ $resource['used'] }}
                        </p>
                    </div>

                    <div class="min-w-0 px-1.5 text-center sm:px-3">
                        <p
                            class="text-[10px] leading-5
                                   text-base-content/45
                                   sm:text-xs"
                        >
                            {{ $resource['availableLabel'] }}
                        </p>

                        <p
                            dir="ltr"
                            title="{{ $resource['available'] }}"
                            class="technical-value mt-1.5
                                   truncate text-xs font-medium
                                   text-base-content
                                   sm:text-sm"
                        >
                            {{ $resource['available'] }}
                        </p>
                    </div>

                    <div class="min-w-0 px-1.5 text-center last:pl-0 sm:px-3">
                        <p
                            class="text-[10px] leading-5
                                   text-base-content/45
                                   sm:text-xs"
                        >
                            مجموع
                        </p>

                        <p
                            dir="ltr"
                            title="{{ $resource['total'] }}"
                            class="technical-value mt-1.5
                                   truncate text-xs font-medium
                                   text-base-content
                                   sm:text-sm"
                        >
                            {{ $resource['total'] }}
                        </p>
                    </div>
                </div>

            </div>

        @endforeach
    </div>

    {{-- Load Average --}}
    <div
        class="border-t border-base-300
               px-4 py-4
               sm:px-6 sm:py-5"
    >
        <div
            class="flex items-center gap-2.5 sm:gap-3"
        >
            <div
                class="flex size-8 shrink-0 items-center
                       justify-center rounded-lg
                       bg-base-200/60
                       sm:size-9"
            >
                <x-icon
                    name="lucide.activity"
                    class="size-4 text-base-content/45"
                />
            </div>

            <div class="min-w-0">
                <h3
                    class="text-sm font-semibold
                           text-base-content"
                >
                    بار سیستم
                </h3>

                <p
                    class="mt-0.5 text-[11px]
                           text-base-content/45
                           sm:text-xs"
                >
                    میانگین بار پردازشی در بازه‌های زمانی مختلف
                </p>
            </div>
        </div>

        <div
            class="mt-4 grid grid-cols-3
                   divide-x divide-x-reverse
                   divide-base-300
                   sm:mt-5"
        >
            <div
                class="flex flex-col items-center
                       justify-center px-2 text-center
                       sm:px-3"
            >
                <p
                    class="technical-value text-sm
                           font-semibold text-base-content
                           sm:text-base"
                    dir="ltr"
                >
                    {{ $loadAverage['oneMinute'] }}
                </p>

                <p
                    class="mt-1 text-[11px]
                           text-base-content/45
                           sm:text-xs"
                >
                    ۱ دقیقه
                </p>
            </div>

            <div
                class="flex flex-col items-center
                       justify-center px-2 text-center
                       sm:px-3"
            >
                <p
                    class="technical-value text-sm
                           font-semibold text-base-content
                           sm:text-base"
                    dir="ltr"
                >
                    {{ $loadAverage['fiveMinutes'] }}
                </p>

                <p
                    class="mt-1 text-[11px]
                           text-base-content/45
                           sm:text-xs"
                >
                    ۵ دقیقه
                </p>
            </div>

            <div
                class="flex flex-col items-center
                       justify-center px-2 text-center
                       sm:px-3"
            >
                <p
                    class="technical-value text-sm
                           font-semibold text-base-content
                           sm:text-base"
                    dir="ltr"
                >
                    {{ $loadAverage['fifteenMinutes'] }}
                </p>

                <p
                    class="mt-1 text-[11px]
                           text-base-content/45
                           sm:text-xs"
                >
                    ۱۵ دقیقه
                </p>
            </div>
        </div>
    </div>
</section>
