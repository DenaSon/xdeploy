<x-card class="shadow-md">

    <div class="flex items-center gap-2">

        <x-icon
            name="o-cpu-chip"
            class="h-5 w-5 text-primary"
        />

        <div>
            <h3 class="font-semibold">
                پردازنده
            </h3>

            <p class="text-sm text-base-content/60">
                CPU Information
            </p>
        </div>

    </div>

    <div class="mt-6 flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">

        {{-- Model --}}
        <div class="flex-1">

            <div class="text-xs text-base-content/60">
                مدل پردازنده
            </div>

            <div class="mt-1 text-lg font-semibold leading-7">
                {{ $cpu['model'] }}
            </div>

        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-6 text-center">

            <div>
                <div class="text-xl font-semibold">
                    {{ $cpu['architecture'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    معماری
                </div>
            </div>

            <div>
                <div class="text-xl font-semibold">
                    {{ $cpu['cores'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    هسته
                </div>
            </div>

            <div>
                <div class="text-xl font-semibold">
                    {{ $cpu['threads'] }}
                </div>

                <div class="mt-1 text-xs text-base-content/60">
                    رشته
                </div>
            </div>

        </div>

    </div>

</x-card>
