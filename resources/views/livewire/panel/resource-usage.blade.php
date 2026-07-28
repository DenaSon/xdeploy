<div class="grid gap-4 lg:grid-cols-3">

    {{-- حافظه --}}
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
        :footer="'مجموع حافظه: ' . $memory['total']"
    />

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
        :footer="'مجموع فضا: ' . $disk['total']"
    />

    {{-- Load Average --}}
    <x-dashboard.card
        title="بار سیستم"
        subtitle="Load Average"
        icon="o-chart-bar"
    >

        <div class="grid grid-cols-3 gap-4">

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
