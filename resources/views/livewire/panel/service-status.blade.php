<x-card
    title="وضعیت سرویس‌ها" class="shadow-md"

>

    <div class="max-h-64 overflow-y-auto scrollbar-thin divide-y divide-base-200 pe-2">

        @foreach($this->services as $service)

            @php($active = $service['status'] === 'active')

            <div class="flex items-center justify-between py-3 transition-colors hover:bg-base-200/40">

                <div class="flex items-center gap-3">

                    <div class="inline-grid *:[grid-area:1/1]">

                        @if($active)
                            <div class="status status-success animate-ping"></div>
                            <div class="status status-success"></div>
                        @else
                            <div class="status status-error"></div>
                        @endif

                    </div>

                    <span class="font-medium">
                        {{ $service['name'] }}
                    </span>

                </div>

                <span @class([
                    'text-xs font-medium',
                    'text-success' => $active,
                    'text-error' => ! $active,
                ])>
                    {{ $active ? 'فعال' : 'غیرفعال' }}
                </span>

            </div>

        @endforeach



    </div>

</x-card>
