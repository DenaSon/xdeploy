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

    $memory = $normalizeResource($memory);
    $disk = $normalizeResource($disk);
    $loadAverage = $normalizeLoadAverage($loadAverage);
@endphp

<div class="grid gap-4 lg:grid-cols-3">

    {{-- Memory --}}
    <x-dashboard.resource-card
        title="حافظه"
        subtitle="RAM"
        icon="o-circle-stack"
        color="success"
        :percent="$memory['usagePercent']"
        :left="$memory['used']"
        leftLabel="استفاده شده"
        :right="$memory['available']"
        rightLabel="قابل استفاده"
        :footer="'مجموع حافظه: '.$memory['total']"
    />

    {{-- Disk --}}
    <x-dashboard.resource-card
        title="فضای دیسک"
        subtitle="/"
        icon="o-server-stack"
        color="warning"
        :percent="$disk['usagePercent']"
        :left="$disk['used']"
        leftLabel="استفاده شده"
        :right="$disk['available']"
        rightLabel="باقی‌مانده"
        :footer="'مجموع فضا: '.$disk['total']"
    />

    {{-- Load average --}}
    <x-dashboard.card
        title="بار سیستم"
        subtitle="Load Average"
        icon="o-chart-bar"
    >
        <div class="grid grid-cols-3 gap-3 sm:gap-4">

            <x-dashboard.stat
                :value="$loadAverage['oneMinute']"
                label="۱ دقیقه"
            />

            <x-dashboard.stat
                :value="$loadAverage['fiveMinutes']"
                label="۵ دقیقه"
            />

            <x-dashboard.stat
                :value="$loadAverage['fifteenMinutes']"
                label="۱۵ دقیقه"
            />

        </div>
    </x-dashboard.card>

</div>
