<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    <div
        wire:init="loadDashboard"
        @if($initialLoadComplete)
            wire:poll.visible.30s="refreshRuntime"
        @endif
        class="space-y-4"
    >
        {{-- Dashboard status --}}
        <div
            class="
                flex min-h-7
                items-center justify-end
                gap-2
                text-[11px]
                text-base-content/35
            "
        >
            <div
                wire:loading.flex
                class="
                    items-center gap-1.5
                    rounded-full
                    border border-base-300
                    bg-base-100
                    px-2.5 py-1
                "
            >
                <span
                    class="
                        loading
                        loading-spinner
                        loading-xs
                    "
                ></span>

                <span>
                    در حال به‌روزرسانی
                </span>
            </div>

            <div
                wire:loading.remove
                class="flex items-center gap-1.5"
            >
                @if($lastUpdatedAt !== null)
                    <x-icon
                        name="lucide.clock-3"
                        class="!size-3.5"
                    />

                    <span>
                        آخرین به‌روزرسانی:
                        <span
                            dir="ltr"
                            class="technical-value"
                        >
                            {{ $lastUpdatedAt }}
                        </span>
                    </span>
                @elseif($hasSnapshot)
                    <x-icon
                        name="lucide.database"
                        class="!size-3.5"
                    />

                    <span>
                        نمایش اطلاعات ذخیره‌شده
                    </span>
                @endif
            </div>
        </div>


        {{-- Global connection state --}}
        @if($connectionErrorMessage !== null)
            <section
                @class([
                    '
                        rounded-2xl
                        border
                        px-4 py-4
                        sm:px-5
                    ',
                    'border-warning/20 bg-warning/5' =>
                        $hasSnapshot,
                    'border-error/20 bg-error/5' =>
                        ! $hasSnapshot,
                ])
            >
                <div
                    class="
                        flex flex-col gap-4
                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                    "
                >
                    <div
                        class="
                            flex min-w-0
                            items-start gap-3
                        "
                    >
                        <div
                            @class([
                                '
                                    flex size-10 shrink-0
                                    items-center justify-center
                                    rounded-xl
                                ',
                                'bg-warning/10 text-warning' =>
                                    $hasSnapshot,
                                'bg-error/10 text-error' =>
                                    ! $hasSnapshot,
                            ])
                        >
                            <x-icon
                                :name="$hasSnapshot
                                    ? 'lucide.cloud-off'
                                    : 'lucide.server-off'"
                                class="!size-5 stroke-[1.8]"
                            />
                        </div>

                        <div class="min-w-0">
                            <h2
                                class="
                                    text-sm font-semibold
                                    text-base-content
                                    sm:text-base
                                "
                            >
                                {{ $connectionErrorTitle }}
                            </h2>

                            <p
                                class="
                                    mt-1
                                    max-w-3xl
                                    text-xs leading-6
                                    text-base-content/55
                                    sm:text-sm
                                "
                            >
                                {{ $connectionErrorMessage }}

                                @if($hasSnapshot)
                                    آخرین اطلاعات موفق همچنان نمایش داده می‌شود.
                                @endif
                            </p>
                        </div>
                    </div>

                    <x-button
                        label="تلاش مجدد"
                        icon="lucide.refresh-cw"
                        wire:click="reloadDashboard"
                        spinner="reloadDashboard"
                        class="
                            btn-outline btn-sm
                            shrink-0
                            self-start
                            rounded-xl
                            sm:self-center
                        "
                    />
                </div>
            </section>
        @endif


        {{-- No data + connection failed --}}
        @if(
            $initialLoadComplete
            && ! $hasSnapshot
            && $connectionErrorMessage !== null
        )
            <section
                class="
                    rounded-2xl
                    border border-base-300
                    bg-base-100
                    px-5 py-12
                    text-center
                "
            >
                <div
                    class="
                        mx-auto
                        flex size-12
                        items-center justify-center
                        rounded-2xl
                        bg-base-200
                        text-base-content/45
                    "
                >
                    <x-icon
                        name="lucide.activity"
                        class="!size-5"
                    />
                </div>

                <h3
                    class="
                        mt-4
                        text-sm font-semibold
                        text-base-content
                    "
                >
                    اطلاعات زنده در دسترس نیست
                </h3>

                <p
                    class="
                        mx-auto mt-1.5
                        max-w-md
                        text-xs leading-6
                        text-base-content/45
                    "
                >
                    پس از برقراری اتصال SSH، اطلاعات داشبورد
                    در یک مرحله دریافت و نمایش داده می‌شود.
                </p>
            </section>

        @else
            <section
                class="
                    grid grid-cols-1
                    gap-5
                    xl:grid-cols-12
                    xl:items-start
                    xl:gap-6
                "
            >
                {{-- Primary server information --}}
                <div
                    class="
                        min-w-0
                        space-y-5
                        xl:col-span-8
                        xl:space-y-6
                    "
                >
                    {{-- Overview --}}
                    @if(isset($sectionErrors['identity']))
                        <x-dashboard.widget-error
                            title="دریافت مشخصات سرور ناموفق بود"
                            :message="$sectionErrors['identity']"
                            retry-action="reloadDashboard"
                        />
                    @elseif(
                        in_array(
                            'identity',
                            $loadedSegments,
                            true,
                        )
                    )
                        <x-dashboard.server-overview
                            :overview="$identity"
                        />
                    @else
                        <x-dashboard.placeholders.card
                            variant="overview"
                        />
                    @endif


                    {{-- CPU --}}
                    @if(isset($sectionErrors['cpu']))
                        <x-dashboard.widget-error
                            title="دریافت اطلاعات CPU ناموفق بود"
                            :message="$sectionErrors['cpu']"
                            retry-action="reloadDashboard"
                        />
                    @elseif(
                        in_array(
                            'cpu',
                            $loadedSegments,
                            true,
                        )
                    )
                        <x-dashboard.cpu-info
                            :cpu="$cpu"
                        />
                    @else
                        <x-dashboard.placeholders.card
                            variant="cpu"
                        />
                    @endif


                    {{-- Resources --}}
                    @if(isset($sectionErrors['resources']))
                        <x-dashboard.widget-error
                            title="دریافت منابع ناموفق بود"
                            :message="$sectionErrors['resources']"
                            retry-action="reloadDashboard"
                        />
                    @elseif(
                        in_array(
                            'resources',
                            $loadedSegments,
                            true,
                        )
                    )
                        <x-dashboard.resource-usage
                            :memory="$resources['memory'] ?? []"
                            :disk="$resources['disk'] ?? []"
                            :load-average="$resources['loadAverage'] ?? []"
                        />
                    @else
                        <x-dashboard.placeholders.card
                            variant="resources"
                        />
                    @endif
                </div>


                {{-- Operational status --}}
                <aside
                    class="
                        min-w-0
                        space-y-5
                        xl:col-span-4
                        xl:space-y-6
                    "
                >
                    {{-- Services --}}
                    @if(isset($sectionErrors['services']))
                        <x-dashboard.widget-error
                            title="دریافت سرویس‌ها ناموفق بود"
                            :message="$sectionErrors['services']"
                            retry-action="reloadDashboard"
                        />
                    @elseif(
                        in_array(
                            'services',
                            $loadedSegments,
                            true,
                        )
                    )
                        <x-dashboard.services-card
                            :services="$services"
                        />
                    @else
                        <x-dashboard.placeholders.card
                            variant="services"
                        />
                    @endif


                    {{-- Docker --}}
                    @if(isset($sectionErrors['docker']))
                        <x-dashboard.widget-error
                            title="دریافت اطلاعات Docker ناموفق بود"
                            :message="$sectionErrors['docker']"
                            retry-action="reloadDashboard"
                        />
                    @elseif(
                        in_array(
                            'docker',
                            $loadedSegments,
                            true,
                        )
                    )
                        @if($docker['installed'] ?? false)
                            <x-dashboard.docker-containers-card
                                :docker="$docker"
                            />
                        @endif
                    @else
                        <x-dashboard.placeholders.card
                            variant="docker"
                        />
                    @endif
                </aside>
            </section>
        @endif
    </div>
</x-servers.workspace>
