@props([
    'overview' => [],
])

@php
    $overview = is_array($overview)
        ? $overview
        : [];

    $displayValue = static function (
        mixed $value,
    ): string|int|float {
        if (
            $value === null
            || $value === ''
        ) {
            return '—';
        }

        if (is_bool($value)) {
            return $value
                ? 'بله'
                : 'خیر';
        }

        if (is_scalar($value)) {
            return $value;
        }

        return '—';
    };

    $generalInformation = [
        [
            'label' => 'نام میزبان',
            'value' => $displayValue(
                $overview['hostname'] ?? null,
            ),
            'icon' => 'lucide.server',
            'technical' => true,
        ],

        [
            'label' => 'سیستم‌عامل',
            'value' => $displayValue(
                $overview['operatingSystem'] ?? null,
            ),
            'icon' => 'lucide.monitor-cog',
            'technical' => false,
        ],

        [
            'label' => 'کرنل',
            'value' => $displayValue(
                $overview['kernel'] ?? null,
            ),
            'icon' => 'lucide.code-xml',
            'technical' => true,
        ],

        [
            'label' => 'کاربر SSH',
            'value' => $displayValue(
                $overview['user'] ?? null,
            ),
            'icon' => 'lucide.user',
            'technical' => true,
        ],

        [
            'label' => 'IP خصوصی',
            'value' => $displayValue(
                $overview['privateIp'] ?? null,
            ),
            'icon' => 'lucide.network',
            'technical' => true,
        ],

        [
            'label' => 'مدت فعالیت',
            'value' => $displayValue(
                $overview['uptime'] ?? null,
            ),
            'icon' => 'lucide.clock-3',
            'technical' => true,
        ],
    ];
@endphp

<section
    class="overflow-hidden rounded-2xl border
           border-base-300 bg-base-100"
>
    <header
        class="flex items-start gap-3
               border-b border-base-300 px-5 py-4 sm:px-6"
    >
        <div
            class="flex size-9 shrink-0 items-center justify-center
                   rounded-xl bg-base-200/70"
        >
            <x-icon
                name="lucide.server-cog"
                class="size-4.5 text-base-content/65"
            />
        </div>

        <div>
            <h2 class="font-semibold text-base-content">
                اطلاعات سرور
            </h2>

            <p
                class="mt-0.5 text-sm text-base-content/50"
            >
                مشخصات سیستم و اطلاعات محیط اجرا
            </p>
        </div>
    </header>

    <div
        class="grid grid-cols-1
               divide-y divide-base-300
               sm:grid-cols-2
               sm:divide-y-0"
    >
        @foreach ($generalInformation as $index => $item)

            <div
                @class([
                    'flex min-w-0 items-start gap-3 px-5 py-4 sm:px-6',
                    'sm:border-b sm:border-base-300' => $index < 4,
                    'sm:border-l sm:border-base-300' => $index % 2 === 0,
                ])
            >
                <div
                    class="mt-0.5 flex size-8 shrink-0
                           items-center justify-center
                           rounded-lg bg-base-200/60"
                >
                    <x-icon
                        :name="$item['icon']"
                        class="size-4 text-base-content/45"
                    />
                </div>

                <div class="min-w-0">

                    <p
                        class="text-xs font-medium
                               text-base-content/45"
                    >
                        {{ $item['label'] }}
                    </p>

                    <p
                        @class([
                            'mt-1 truncate text-sm font-medium text-base-content',
                            'technical-value' => $item['technical'],
                        ])
                        @if ($item['technical'])
                            dir="ltr"
                        @endif
                    >
                        {{ $item['value'] }}
                    </p>

                </div>
            </div>

        @endforeach
    </div>
</section>
