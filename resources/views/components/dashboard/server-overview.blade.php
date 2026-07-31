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
        'Hostname' => $displayValue(
            $overview['hostname'] ?? null,
        ),

        'Operating System' => $displayValue(
            $overview['operatingSystem'] ?? null,
        ),

        'Kernel' => $displayValue(
            $overview['kernel'] ?? null,
        ),

        'User' => $displayValue(
            $overview['user'] ?? null,
        ),

        'Private IP' => $displayValue(
            $overview['privateIp'] ?? null,
        ),

        'Uptime' => $displayValue(
            $overview['uptime'] ?? null,
        ),
    ];
@endphp

<x-dashboard.card
    title="اطلاعات سرور"
    subtitle="مشخصات کلی و وضعیت فعلی سرور"
    icon="o-server"
>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">

        @foreach ($generalInformation as $label => $value)

            <x-dashboard.stat
                :label="$label"
                :value="$value"
                align="right"
            />

        @endforeach

    </div>
</x-dashboard.card>
