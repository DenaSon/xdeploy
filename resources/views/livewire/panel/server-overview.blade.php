<x-card
    title="اطلاعات سرور"
    subtitle="مشخصات کلی سرور"
    class="shadow-md"
>

    <x-slot:menu>
        <x-icon
            name="o-server"
            class="h-5 w-5 text-primary"
        />
    </x-slot:menu>

    <div class="grid grid-cols-2 gap-3">

        @foreach($this->generalInformation() as $label => $value)

            <div class="rounded-lg border border-base-300 bg-base-200/40 p-2">

                <div class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                    {{ $label }}
                </div>

                <div class="mt-1 break-all text-sm font-semibold leading-6">
                    {{ $value }}
                </div>

            </div>

        @endforeach

    </div>

</x-card>
