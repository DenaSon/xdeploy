<x-dashboard.card
    title="اطلاعات سرور"
    subtitle="مشخصات کلی سرور"
    icon="o-server"
>

    <div class="grid grid-cols-2 gap-4">

        @foreach($this->generalInformation() as $label => $value)

            <x-dashboard.stat
                :value="$value"
                :label="$label"
                align="right"
            />

        @endforeach

    </div>

</x-dashboard.card>
