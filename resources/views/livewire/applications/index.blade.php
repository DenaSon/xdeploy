<x-servers.workspace :server="$server">

    <section class="space-y-5">

        {{-- Page header --}}
        <header
            class="flex flex-col gap-4
           sm:flex-row sm:items-center sm:justify-between"
        >
            {{-- Title --}}
            <div
                class="flex min-w-0 items-start gap-3.5"
            >
                <div
                    class="flex size-10 shrink-0
                   items-center justify-center
                   rounded-xl
                   border border-base-300
                   bg-base-100"
                >
                    <x-icon
                        name="lucide.blocks"
                        class="size-4.5 text-primary"
                    />
                </div>

                <div class="min-w-0">

                    <div
                        class="flex flex-wrap items-center gap-2"
                    >
                        <h2
                            class="text-lg font-semibold
                           tracking-tight text-base-content"
                        >
                            برنامه‌ها
                        </h2>

                        <span
                            class="text-xs font-medium
                           text-base-content/35"
                        >
                    کاتالوگ xDeploy
                </span>
                    </div>


                </div>
            </div>

            {{-- Catalog metadata --}}
            @if ($applications !== [])

                <div
                    class="inline-flex w-fit shrink-0
                   items-center gap-2
                   rounded-xl
                   bg-base-200/55
                   px-3 py-2"
                >
                    <div
                        class="flex size-6 items-center
                       justify-center rounded-lg
                       bg-base-100"
                    >
                        <x-icon
                            name="lucide.package-check"
                            class="size-3.5 text-base-content/45"
                        />
                    </div>

                    <div
                        class="flex items-baseline gap-1"
                    >
                <span
                    class="text-sm font-semibold
                           text-base-content"
                >
                    {{ count($applications) }}
                </span>

                        <span
                            class="text-xs
                           text-base-content/45"
                        >
                    برنامه در دسترس
                </span>
                    </div>
                </div>

            @endif
        </header>
        <x-hr/>

        {{-- Catalog --}}
        <div
            class="grid grid-cols-1 gap-4
                   md:grid-cols-2
                   xl:grid-cols-3"
        >
            @forelse ($applications as $application)

                <x-applications.card
                    :application="$application"
                    :server-id="$serverId"
                    wire:key="application-catalog-{{ $application['slug'] }}"
                />

            @empty

                <div
                    class="col-span-full rounded-2xl
                           border border-base-300 bg-base-100
                           px-6 py-10 text-center"
                >
                    <div
                        class="mx-auto flex size-10 items-center
                               justify-center rounded-xl
                               bg-base-200/60"
                    >
                        <x-icon
                            name="lucide.package-open"
                            class="size-4.5 text-base-content/35"
                        />
                    </div>

                    <h3
                        class="mt-3 text-sm font-semibold
                               text-base-content"
                    >
                        برنامه‌ای برای نمایش وجود ندارد
                    </h3>

                    <p
                        class="mx-auto mt-1.5 max-w-md
                               text-sm leading-6
                               text-base-content/50"
                    >
                        در حال حاضر برنامه منتشرشده‌ای
                        در کاتالوگ xDeploy وجود ندارد.
                    </p>
                </div>

            @endforelse
        </div>

    </section>

</x-servers.workspace>
