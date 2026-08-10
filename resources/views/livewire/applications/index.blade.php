<x-servers.workspace :server="$server">
    <section class="space-y-5">
        <header
            class="flex flex-col gap-3
                   sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2
                    class="text-lg font-semibold tracking-tight
                           text-base-content"
                >
                    برنامه‌ها
                </h2>

                <p
                    class="mt-1 text-sm leading-6
                           text-base-content/50"
                >
                    برنامه موردنظر را برای نصب یا مدیریت روی این سرور انتخاب کنید.
                </p>
            </div>

            @if ($applications !== [])
                <span
                    class="inline-flex w-fit items-center
                           rounded-full border border-base-300
                           bg-base-100 px-2.5 py-1
                           text-xs font-medium text-base-content/55"
                >
                    {{ count($applications) }} برنامه
                </span>
            @endif
        </header>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                           px-5 py-12 text-center sm:px-6"
                >
                    <div
                        class="mx-auto flex size-10 items-center
                               justify-center rounded-xl bg-base-200/60"
                    >
                        <x-icon
                            name="lucide.package-open"
                            class="size-4.5 text-base-content/35"
                        />
                    </div>

                    <h3
                        class="mt-3 text-sm font-medium
                               text-base-content"
                    >
                        برنامه‌ای برای نمایش وجود ندارد
                    </h3>

                    <p
                        class="mx-auto mt-1.5 max-w-sm
                               text-xs leading-6 text-base-content/50"
                    >
                        در حال حاضر برنامه منتشرشده و قابل پشتیبانی در کاتالوگ xDeploy وجود ندارد.
                    </p>
                </div>
            @endforelse
        </div>
    </section>
</x-servers.workspace>
