<x-dashboard.card
    title="پردازنده"
    subtitle="CPU Information"
    icon="o-cpu-chip"
>

    <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

        {{-- Model --}}
        <div class="flex-1">

            <div class="text-xs font-medium tracking-wide text-base-content/50">
                مدل پردازنده
            </div>

            <div class="mt-2 text-base font-semibold leading-6 sm:text-lg sm:leading-7">
                {{ $cpu['model'] }}
            </div>

        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3 sm:gap-4">

            <x-dashboard.stat
                :value="$cpu['architecture']"
                label="معماری"
            />

            <x-dashboard.stat
                :value="$cpu['cores']"
                label="هسته"
            />

            <x-dashboard.stat
                :value="$cpu['threads']"
                label="رشته"
            />

        </div>

    </div>

</x-dashboard.card>
