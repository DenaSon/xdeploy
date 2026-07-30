@props([
    'application',
])

<a
    href="{{ route('panel.applications.show', ['application' => $application['type']]) }}"
    class="group black"
>
    <x-card
        class="border border-base-300 bg-base-100 shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-xl"
    >

        <div class="flex items-center gap-4">

            <div class="flex size-12 items-center justify-center rounded-xl bg-primary/10">
                <x-icon
                    name="o-cube"
                    class="size-6 text-primary"
                />
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="truncate text-lg text-base font-semibold">
                    {{ $application['name'] }}
                </h2>

                <p class="text-sm text-base-content/60">
                    {{ $application['type'] }}
                </p>
            </div>

            <x-icon
                name="o-chevron-left"
                class="size-5 text-base-content/40 transition-transform duration-200 group-hover:-translate-x-1"
            />

        </div>

    </x-card>
</a>
