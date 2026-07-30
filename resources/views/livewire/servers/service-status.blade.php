<x-dashboard.card
    title="وضعیت سرویس‌ها"
    subtitle="Services"
    icon="o-bolt"
>

    <div class="space-y-3 max-h-64 overflow-y-auto scrollbar-thin">

        @foreach($this->services as $service)

            <x-dashboard.service-status
                :name="$service['name']"
                :active="$service['status'] === 'active'"
            />

        @endforeach

    </div>

</x-dashboard.card>
