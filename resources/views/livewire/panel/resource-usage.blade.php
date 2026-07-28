<div class="grid gap-4 lg:grid-cols-3" >

    {{-- حافظه --}}
    <x-card  class="shadow-md">

        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <x-icon name="o-circle-stack" class="h-5 w-5 text-success" />

                    <h3 class="font-semibold">
                        حافظه
                    </h3>
                </div>

                <p class="mt-1 text-sm text-base-content/60">
                    RAM
                </p>
            </div>

            <div class="text-right">
                <div class="text-3xl font-bold">
                    {{ $memory['usagePercent'] }}٪
                </div>

                <div class="text-xs text-base-content/60">
                    مصرف
                </div>
            </div>
        </div>

        <progress
            class="progress progress-success mt-5 h-2 w-full"
            value="{{ $memory['usagePercent'] }}"
            max="100">
        </progress>

        <div class="mt-6 grid grid-cols-2 gap-4">

            <div>
                <div class="text-2xl font-semibold">
                    {{ $memory['used'] }}
                </div>

                <div class="text-sm text-base-content/60">
                    استفاده شده
                </div>
            </div>

            <div class="text-left">
                <div class="text-2xl font-semibold">
                    {{ $memory['available'] }}
                </div>

                <div class="text-sm text-base-content/60">
                    قابل استفاده
                </div>
            </div>

        </div>

        <div class="mt-4 border-t pt-3 text-sm text-base-content/60">
            مجموع حافظه: {{ $memory['total'] }}
        </div>

    </x-card>



    {{-- دیسک --}}
    <x-card class="shadow-md">

        <div class="flex items-start justify-between">

            <div>
                <div class="flex items-center gap-2">
                    <x-icon name="o-server-stack" class="h-5 w-5 text-warning" />

                    <h3 class="font-semibold">
                        فضای دیسک
                    </h3>
                </div>

                <p class="mt-1 text-sm text-base-content/60">
                    /
                </p>
            </div>

            <div class="text-right">
                <div class="text-3xl font-bold">
                    {{ $disk['usagePercent'] }}٪
                </div>

                <div class="text-xs text-base-content/60">
                    مصرف
                </div>
            </div>

        </div>

        <progress
            class="progress progress-warning mt-5 h-2 w-full"
            value="{{ $disk['usagePercent'] }}"
            max="100">
        </progress>

        <div class="mt-6 grid grid-cols-2 gap-4">

            <div>
                <div class="text-2xl font-semibold">
                    {{ $disk['used'] }}
                </div>

                <div class="text-sm text-base-content/60">
                    استفاده شده
                </div>
            </div>

            <div class="text-left">
                <div class="text-2xl font-semibold">
                    {{ $disk['available'] }}
                </div>

                <div class="text-sm text-base-content/60">
                    باقی‌مانده
                </div>
            </div>

        </div>

        <div class="mt-4 border-t pt-3 text-sm text-base-content/60">
            مجموع فضا: {{ $disk['total'] }}
        </div>

    </x-card>



    {{-- Load Average --}}
    <x-card class="shadow-md">

        <div class="flex items-center gap-2">

            <x-icon
                name="o-chart-bar"
                class="h-5 w-5 text-info"
            />

            <div>
                <h3 class="font-semibold">
                    بار سیستم
                </h3>

                <p class="text-sm text-base-content/60">
                    Load Average
                </p>
            </div>

        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 text-center">

            <div>
                <div class="text-2xl font-semibold">
                    {{ $loadAverage['oneMinute'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    ۱ دقیقه
                </div>
            </div>

            <div>
                <div class="text-2xl font-semibold">
                    {{ $loadAverage['fiveMinutes'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    ۵ دقیقه
                </div>
            </div>

            <div>
                <div class="text-2xl font-semibold">
                    {{ $loadAverage['fifteenMinutes'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    ۱۵ دقیقه
                </div>
            </div>

        </div>

    </x-card>

</div>
