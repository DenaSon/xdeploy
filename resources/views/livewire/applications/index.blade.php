<x-servers.workspace :server="$server">

    <section
        @if(count($applications) > 3)
            x-data="{ query: '' }"
        @endif
        class="space-y-6"
    >
        {{-- Page header --}}
        <header
            class="
                flex flex-col gap-5

                sm:flex-row
                sm:items-end
                sm:justify-between
            "
        >
            {{-- Identity --}}
            <div
                class="
                    flex min-w-0
                    items-start gap-3.5
                "
            >
                <span
                    class="
                        flex size-10 shrink-0
                        items-center justify-center

                        rounded-xl

                        bg-primary/10
                        text-primary

                        ring-1 ring-primary/10
                    "
                >
                    <x-icon
                        name="lucide.blocks"
                        class="!size-[18px] stroke-[1.8]"
                    />
                </span>


                <div class="min-w-0">
                    <div
                        class="
                            flex flex-wrap
                            items-center gap-2.5
                        "
                    >
                        <h1
                            class="
                                text-xl
                                font-semibold
                                tracking-tight
                                text-base-content

                                sm:text-2xl
                            "
                        >
                            برنامه‌ها
                        </h1>


                        @if($applications !== [])
                            <span
                                class="
                                    inline-flex
                                    min-w-6
                                    items-center justify-center

                                    rounded-full
                                    bg-base-200

                                    px-2 py-0.5

                                    text-[11px]
                                    font-medium
                                    text-base-content/45
                                "
                            >
                                {{ count($applications) }}
                            </span>
                        @endif
                    </div>


                    <p
                        class="
                            mt-1

                            max-w-xl

                            text-xs
                            leading-6
                            text-base-content/45

                            sm:text-sm
                        "
                    >
                        برنامه‌های پشتیبانی‌شده را روی این سرور
                        نصب، راه‌اندازی و مدیریت کنید.
                    </p>
                </div>
            </div>


            {{-- Catalog metadata --}}
            @if($applications !== [])
                <div
                    class="
                        flex
                        shrink-0
                        items-center gap-2.5

                        self-start

                        rounded-xl
                        border border-base-300/70
                        bg-base-100

                        px-3 py-2

                        sm:self-auto
                    "
                >
                    <span
                        class="
                            flex size-7
                            items-center justify-center

                            rounded-lg
                            bg-success/[0.08]
                            text-success
                        "
                    >
                        <x-icon
                            name="lucide.package-check"
                            class="!size-3.5 stroke-[1.8]"
                        />
                    </span>

                    <div>
                        <div
                            class="
                                text-[10px]
                                text-base-content/35
                            "
                        >
                            کاتالوگ برنامه‌ها
                        </div>

                        <div
                            class="
                                mt-0.5

                                text-xs
                                font-medium
                                text-base-content/65
                            "
                        >
                            {{ count($applications) }}
                            برنامه در دسترس
                        </div>
                    </div>
                </div>
            @endif
        </header>


        {{-- Catalog toolbar --}}
        @if(count($applications) > 3)
            <div
                class="
                    flex
                    items-center gap-3

                    border-y border-base-300/60

                    py-3
                "
            >
                <label
                    class="
                        relative
                        block
                        w-full max-w-sm
                    "
                >
                    <span
                        class="
                            pointer-events-none

                            absolute
                            inset-y-0 start-3

                            flex items-center

                            text-base-content/30
                        "
                    >
                        <x-icon
                            name="lucide.search"
                            class="!size-4 stroke-[1.7]"
                        />
                    </span>

                    <input
                        type="search"
                        x-model.debounce.150ms="query"
                        placeholder="جستجو در برنامه‌ها"
                        aria-label="جستجو در برنامه‌ها"
                        class="
                            input
                            input-sm

                            w-full

                            rounded-xl

                            border-base-300
                            bg-base-100

                            ps-9

                            text-xs

                            placeholder:text-base-content/30

                            focus:border-primary/30
                            focus:outline-none
                        "
                    />
                </label>


                <span
                    class="
                        hidden
                        shrink-0

                        text-[11px]
                        text-base-content/30

                        sm:inline
                    "
                >
                    برنامه موردنظر را انتخاب کنید
                </span>
            </div>

        @else
            <div
                class="
                    h-px
                    bg-base-300/60
                "
            ></div>
        @endif


        {{-- Catalog --}}
        <div
            class="
                grid grid-cols-1
                gap-4

                md:grid-cols-2

                xl:grid-cols-3
            "
        >
            @forelse($applications as $application)

                @php
                    $searchableApplicationText = mb_strtolower(
                        implode(
                            ' ',
                            [
                                $application['name'] ?? '',
                                $application['short_description'] ?? '',
                                $application['slug'] ?? '',
                            ],
                        ),
                    );
                @endphp

                <div
                    @if(count($applications) > 3)
                        x-show="
                            query.trim() === ''
                            || @js($searchableApplicationText)
                                .includes(query.trim().toLocaleLowerCase('fa'))
                        "
                    x-transition.opacity.duration.150ms
                    @endif
                    wire:key="application-catalog-wrapper-{{ $application['slug'] }}"
                >
                    <x-applications.card
                        :application="$application"
                        :server-id="$serverId"
                        wire:key="application-catalog-{{ $application['slug'] }}"
                    />
                </div>

            @empty
                {{-- Empty state --}}
                <div
                    class="
                        col-span-full

                        relative
                        overflow-hidden

                        rounded-2xl

                        border border-base-300/80
                        bg-base-100

                        px-6 py-12

                        text-center
                    "
                >
                    <div
                        aria-hidden="true"
                        class="
                            pointer-events-none

                            absolute
                            start-1/2 top-6

                            size-56
                            -translate-x-1/2

                            rounded-full
                            bg-primary/[0.045]
                            blur-3xl
                        "
                    ></div>


                    <div
                        class="
                            relative

                            mx-auto
                            max-w-md
                        "
                    >
                        <span
                            class="
                                mx-auto

                                flex size-12
                                items-center justify-center

                                rounded-2xl

                                bg-base-200/60
                                text-base-content/35
                            "
                        >
                            <x-icon
                                name="lucide.package-open"
                                class="!size-5 stroke-[1.7]"
                            />
                        </span>


                        <h2
                            class="
                                mt-4

                                text-sm
                                font-semibold
                                text-base-content
                            "
                        >
                            برنامه‌ای برای نمایش وجود ندارد
                        </h2>


                        <p
                            class="
                                mx-auto mt-1.5
                                max-w-sm

                                text-sm
                                leading-7
                                text-base-content/45
                            "
                        >
                            در حال حاضر برنامه منتشرشده‌ای
                            در کاتالوگ در دسترس نیست.
                        </p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

</x-servers.workspace>
