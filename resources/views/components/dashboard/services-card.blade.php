@props([
    'services' => [],
])

@php
    $services = is_array($services)
        ? array_values(array_filter(
            $services,
            static fn (mixed $service): bool =>
                is_array($service)
                && isset(
                    $service['name'],
                    $service['status'],
                ),
        ))
        : [];
@endphp

<div class="h-full w-full min-w-0">

    <x-dashboard.card
        title="وضعیت سرویس‌ها"
        subtitle="Services"
        icon="o-bolt"
        class="h-full w-full min-w-0"
    >
        <div
            class="max-h-64 w-full min-w-0 space-y-3
                   overflow-x-hidden overflow-y-auto scrollbar-thin"
        >

            @forelse ($services as $service)

                <div
                    class="w-full min-w-0"
                    wire:key="service-{{ md5((string) $service['name']) }}"
                >
                    <x-dashboard.service-status
                        :name="(string) $service['name']"
                        :active="$service['status'] === 'active'"
                    />
                </div>

            @empty

                <div class="w-full py-8 text-center">

                    <div
                        class="mx-auto flex size-11 items-center justify-center
                               rounded-2xl bg-base-200"
                    >
                        <x-icon
                            name="o-bolt"
                            class="size-5 text-base-content/30"
                        />
                    </div>

                    <p class="mt-3 text-sm text-base-content/50">
                        اطلاعاتی از سرویس‌های سیستم دریافت نشد.
                    </p>

                </div>

            @endforelse

        </div>
    </x-dashboard.card>

</div>
