<div>
    <x-header
        title="ماژول‌ها"

    />

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">

        @forelse ($modules as $module)

            <x-modules.card :module="$module" />

        @empty

            <div class="col-span-full">

                <x-card class="py-12 text-center">

                    <x-icon
                        name="o-cube"
                        class="mx-auto h-12 w-12 text-base-content/30"
                    />

                    <h3 class="mt-4 text-lg font-semibold">
                        هیچ ماژولی یافت نشد.
                    </h3>

                    <p class="mt-2 text-base-content/60">
                        در حال حاضر هیچ ماژولی برای نمایش وجود ندارد.
                    </p>

                </x-card>

            </div>

        @endforelse

    </div>
</div>
