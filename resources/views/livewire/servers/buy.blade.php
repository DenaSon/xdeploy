<div
    dir="rtl"
    wire:init="loadCatalog"
    data-buy-form
    data-livewire-request-context="initialization"
    data-livewire-request-feedback="inline"
    x-data="{
        catalogReady: false,
        catalogLoadFailed: false,
        minimumDelayPassed: false,
        showInitialLoader: true,

        finishInitialLoader() {
            if (this.catalogReady && this.minimumDelayPassed) {
                this.showInitialLoader = false;
            }
        },

        markCatalogReady() {
            this.catalogReady = true;
            this.catalogLoadFailed = false;
            this.finishInitialLoader();
        },
    }"
    x-init="
        if ($wire.catalogLoaded) {
            markCatalogReady();
        }

        $wire.$watch('catalogLoaded', (value) => {
            if (value) {
                markCatalogReady();
            }
        });

        setTimeout(() => {
            minimumDelayPassed = true;
            finishInitialLoader();
        }, 1200);
    "
    x-on:xdeploy-livewire-request-failed.window="
        const actions = $event.detail?.actions ?? [];

        if (
            actions.includes('loadCatalog')
            || actions.includes('reloadCatalog')
        ) {
            catalogLoadFailed = true;
            showInitialLoader = false;
        }
    "
>
    {{-- Initial catalog loader --}}
    <div
        x-show="showInitialLoader"
        x-transition:leave="transition ease-out duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="
            fixed inset-0 z-70
            flex items-center justify-center
            bg-base-100/90
            opacity-60
            backdrop-blur-[2px]
        "
        role="status"
        aria-live="polite"
        aria-label="در حال آماده‌سازی اطلاعات سرور"
    >
        <div class="flex flex-col items-center gap-3">
            <div
                class="
                    flex size-14
                    items-center justify-center
                    rounded-2xl
                    border border-base-300
                    bg-base-100 shadow-sm
                "
            >
                <span
                    class="
                        loading loading-spinner
                        loading-md text-primary
                    "
                ></span>
            </div>

            <div class="text-center">
                <div
                    class="
                        text-sm font-medium
                        text-base-content/70
                    "
                >
                    در حال دریافت پلن‌ها
                </div>

                <div
                    class="
                        mt-1 text-[11px]
                        text-base-content/35
                    "
                >
                    Loading...
                </div>
            </div>
        </div>
    </div>

    @if(! $catalogLoaded)
        <section
            x-cloak
            x-show="catalogLoadFailed"
            style="display: none"
            class="
                rounded-2xl
                border border-warning/20
                bg-base-100
                px-5 py-9
                text-center
            "
            role="status"
            aria-live="polite"
        >
            <div
                class="
                    mx-auto flex size-11
                    items-center justify-center
                    rounded-xl
                    bg-warning/10 text-warning
                "
            >
                <x-icon
                    name="lucide.wifi-off"
                    class="!size-5"
                />
            </div>

            <h2
                class="
                    mt-3 font-semibold
                    text-base-content
                "
            >
                بارگذاری اطلاعات کامل نشد
            </h2>

            <p
                class="
                    mx-auto mt-1.5
                    max-w-md
                    text-sm leading-6
                    text-base-content/50
                "
            >
                دریافت اطلاعات کامل نشد. وضعیت اتصال را بررسی کنید و دوباره تلاش کنید.
            </p>

            <x-button
                label="تلاش دوباره"
                icon="lucide.refresh-cw"
                wire:click="reloadCatalog"
                wire:target="reloadCatalog"
                data-livewire-request-context="initialization"
                data-livewire-request-feedback="inline"
                x-on:click="
                    catalogLoadFailed = false;
                    showInitialLoader = true;
                "
                spinner
                class="
                    btn-primary btn-sm
                    mt-4 rounded-xl
                "
            />
        </section>

        <div
            x-show="! catalogLoadFailed"
            data-buy-skeleton
            class="
                grid gap-4
                md:grid-cols-[minmax(0,1fr)_320px]
            "
        >
            <section
                class="
                    rounded-2xl
                    border border-base-300
                    bg-base-100 p-4
                "
            >
                <div class="space-y-5 animate-pulse">
                    <div>
                        <div class="h-3 w-24 rounded bg-base-300"></div>
                        <div class="mt-2 h-11 rounded-xl bg-base-200"></div>
                    </div>

                    <div>
                        <div class="h-3 w-20 rounded bg-base-300"></div>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <div class="h-14 rounded-xl bg-base-200"></div>
                            <div class="h-14 rounded-xl bg-base-200"></div>
                            <div class="h-14 rounded-xl bg-base-200"></div>
                        </div>
                    </div>

                    <div>
                        <div class="h-3 w-16 rounded bg-base-300"></div>
                        <div class="mt-2 h-24 rounded-xl bg-base-200"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="h-16 rounded-xl bg-base-200"></div>
                        <div class="h-16 rounded-xl bg-base-200"></div>
                    </div>
                </div>
            </section>

            <aside
                class="
                    hidden h-72
                    rounded-2xl
                    border border-base-300
                    bg-base-100
                    md:block
                "
                aria-hidden="true"
            ></aside>
        </div>
    @elseif($catalogError && $regions === [])
        <section
            class="
                rounded-2xl
                border border-error/20
                bg-base-100
                px-5 py-9
                text-center
            "
        >
            <div
                class="
                    mx-auto flex size-11
                    items-center justify-center
                    rounded-xl
                    bg-error/10 text-error
                "
            >
                <x-icon
                    name="lucide.cloud-off"
                    class="!size-5"
                />
            </div>

            <h2
                class="
                    mt-3 font-semibold
                    text-base-content
                "
            >
                دریافت اطلاعات سرورها انجام نشد
            </h2>

            <p
                class="
                    mx-auto mt-1.5
                    max-w-md
                    text-sm leading-6
                    text-base-content/50
                "
            >
                {{ $catalogError }}
            </p>

            <x-button
                label="تلاش دوباره"
                icon="lucide.refresh-cw"
                wire:click="reloadCatalog"
                wire:target="reloadCatalog"
                data-livewire-request-context="initialization"
                data-livewire-request-feedback="inline"
                spinner
                class="
                    btn-primary btn-sm
                    mt-4 rounded-xl
                "
            />
        </section>
    @else
        <div
            data-buy-main-layout
            class="
                grid gap-4 pb-28
                md:grid-cols-[minmax(0,1fr)_320px]
                md:pb-0
            "
        >
            <main class="min-w-0">
                <section
                    class="
                        overflow-hidden
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                    "
                >
                    {{-- Location --}}
                    <div
                        class="
                            grid gap-3
                            border-b border-base-300
                            p-4
                            lg:grid-cols-[140px_minmax(0,1fr)]
                            lg:items-center
                        "
                    >
                        <div>
                            <div
                                class="
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                موقعیت
                            </div>

                            <div
                                class="
                                    mt-0.5 text-xs
                                    text-base-content/40
                                "
                            >
                                موقعیت استقرار
                            </div>
                        </div>

                        <div
                            class="
                                flex min-w-0
                                flex-col gap-2
                                sm:flex-row sm:items-center
                            "
                        >
                            <div
                                class="
                                    inline-grid shrink-0
                                    grid-cols-2 gap-1
                                    rounded-xl
                                    bg-base-200/70 p-1
                                "
                            >
                                <button
                                    type="button"
                                    wire:click="selectRegionGroup('iran')"
                                    wire:target="selectRegionGroup,selectRegion"
                                    wire:loading.attr="disabled"
                                    @disabled(
                                        ($regionGroupCounts['iran'] ?? 0) === 0
                                    )
                                    @class([
                                        '
                                            btn btn-sm
                                            h-11 min-h-11
                                            cursor-pointer
                                            rounded-lg border-0
                                            px-3 text-xs font-medium
                                            sm:h-8 sm:min-h-8
                                        ',
                                        'btn-primary text-primary-content' =>
                                            $regionGroup === 'iran',
                                        'btn-ghost text-base-content/50 hover:bg-base-300/50' =>
                                            $regionGroup !== 'iran',
                                        'opacity-40 cursor-not-allowed' =>
                                            ($regionGroupCounts['iran'] ?? 0) === 0,
                                    ])
                                >
                                    ایران
                                </button>

                                <button
                                    type="button"
                                    wire:click="selectRegionGroup('international')"
                                    wire:target="selectRegionGroup,selectRegion"
                                    wire:loading.attr="disabled"
                                    @disabled(
                                        ($regionGroupCounts['international'] ?? 0) === 0
                                    )
                                    @class([
                                        '
                                            btn btn-sm
                                            h-11 min-h-11
                                            cursor-pointer
                                            rounded-lg border-0
                                            px-3 text-xs font-medium
                                            sm:h-8 sm:min-h-8
                                        ',
                                        'btn-primary text-primary-content' =>
                                            $regionGroup === 'international',
                                        'btn-ghost text-base-content/50 hover:bg-base-300/50' =>
                                            $regionGroup !== 'international',
                                        'opacity-40 cursor-not-allowed' =>
                                            ($regionGroupCounts['international'] ?? 0) === 0,
                                    ])
                                >
                                    آلمان
                                </button>
                            </div>

                            <div class="relative min-w-0 flex-1">
                                <select
                                    wire:change="selectRegion($event.target.value)"
                                    wire:target="selectRegionGroup,selectRegion"
                                    wire:loading.attr="disabled"
                                    class="
                                        select select-bordered
                                        h-11 min-h-11 w-full
                                        cursor-pointer rounded-xl
                                        border-base-300
                                        bg-base-100 text-sm
                                        focus:outline-none
                                        sm:h-10 sm:min-h-10
                                    "
                                >
                                    @foreach($visibleRegions as $region)
                                        <option
                                            value="{{ $region['id'] }}"
                                            @selected(
                                                $regionId === $region['id']
                                            )
                                        >
                                            {{ $this->regionDisplayName($region) }}
                                        </option>
                                    @endforeach
                                </select>

                                <div
                                    wire:loading
                                    wire:target="selectRegionGroup,selectRegion"
                                    class="
                                        pointer-events-none
                                        absolute inset-y-0 left-3
                                        flex items-center
                                    "
                                >
                                    <span
                                        class="
                                            loading loading-spinner
                                            loading-xs text-primary
                                        "
                                    ></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Billing period --}}
                    <div
                        class="
                            grid gap-3
                            border-b border-base-300
                            p-4
                            lg:grid-cols-[140px_minmax(0,1fr)]
                            lg:items-center
                        "
                    >
                        <div>
                            <div
                                class="
                                    flex items-center gap-2
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                مدت استفاده

                                <span
                                    wire:loading
                                    wire:target="selectPeriod"
                                    class="
                                        loading loading-spinner
                                        loading-xs text-primary
                                    "
                                ></span>
                            </div>

                            <div
                                class="
                                    mt-0.5 text-xs
                                    text-base-content/40
                                "
                            >
                                دوره پرداخت
                            </div>
                        </div>

                        <div
                            class="
                                grid grid-cols-3 gap-1.5
                                rounded-xl
                                bg-base-200/55 p-1.5
                            "
                        >
                            @foreach($periods as $periodOption)
                                <button
                                    type="button"
                                    wire:key="period-{{ $periodOption['id'] }}"
                                    wire:click="selectPeriod('{{ $periodOption['id'] }}')"
                                    wire:target="selectPeriod"
                                    wire:loading.attr="disabled"
                                    @class([
                                        '
                                            relative
                                            btn h-auto min-h-14
                                            cursor-pointer
                                            flex-col gap-0.5
                                            rounded-lg border
                                            px-2 py-2
                                            text-center
                                            transition-all duration-150
                                        ',
                                        '
                                            btn-primary
                                            border-primary
                                            text-primary-content
                                            ring-2 ring-primary/15
                                            shadow-sm
                                        ' => $period === $periodOption['id'],
                                        '
                                            btn-ghost
                                            border-transparent
                                            text-base-content/50
                                            hover:border-base-300
                                            hover:bg-base-100
                                            hover:text-base-content
                                        ' => $period !== $periodOption['id'],
                                    ])
                                >
                                    @if($period === $periodOption['id'])
                                        <span
                                            class="
                                                absolute left-2 top-2
                                                flex size-4
                                                items-center justify-center
                                                rounded-full
                                                bg-primary-content/15
                                            "
                                        >
                                            <x-icon
                                                name="lucide.check"
                                                class="
                                                    !size-3
                                                    text-primary-content
                                                "
                                            />
                                        </span>
                                    @endif

                                    <div
                                        class="
                                            text-xs font-semibold
                                            sm:text-sm
                                        "
                                    >
                                        {{ $periodOption['label'] }}
                                    </div>

                                    <div
                                        @class([
                                            '
                                                mt-0.5 hidden
                                                text-[10px]
                                                sm:block
                                            ',
                                            'text-primary-content/70' =>
                                                $period === $periodOption['id'],
                                            'text-base-content/35' =>
                                                $period !== $periodOption['id'],
                                        ])
                                    >
                                        {{ $periodOption['hint'] }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Plan --}}
                    <div
                        class="
                            grid gap-3
                            border-b border-base-300
                            p-4
                            lg:grid-cols-[140px_minmax(0,1fr)]
                            lg:items-start
                        "
                    >
                        <div>
                            <div
                                class="
                                    flex items-center gap-2
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                پلن

                                <span
                                    wire:loading
                                    wire:target="selectSize"
                                    class="
                                        loading loading-spinner
                                        loading-xs text-primary
                                    "
                                ></span>
                            </div>

                            <div
                                class="
                                    mt-0.5 text-xs
                                    text-base-content/40
                                "
                            >
                                منابع سرور
                            </div>
                        </div>

                        <div class="min-w-0">
                            <div
                                class="relative"
                                wire:loading.class="opacity-60"
                                wire:target="selectSize"
                            >
                                <select
                                    wire:change="selectSize($event.target.value)"
                                    wire:target="selectSize"
                                    wire:loading.attr="disabled"
                                    class="
                                        select select-bordered
                                        h-11 min-h-11 w-full
                                        cursor-pointer rounded-xl
                                        border-base-300
                                        bg-base-100
                                        text-sm font-medium
                                        transition-colors duration-150
                                        hover:border-primary/30
                                        focus:border-primary
                                        focus:outline-none
                                        sm:h-10 sm:min-h-10
                                    "
                                >
                                    @foreach($sizes as $size)
                                        <option
                                            value="{{ $size['id'] }}"
                                            @selected(
                                                $sizeId === $size['id']
                                            )
                                        >
                                            {{ $size['name'] }}
                                            — {{ $size['vcpu'] }} vCPU
                                            / {{ $size['memory_label'] }}
                                        </option>
                                    @endforeach
                                </select>

                                <div
                                    wire:loading
                                    wire:target="selectSize"
                                    class="
                                        pointer-events-none
                                        absolute inset-y-0 left-3
                                        flex items-center
                                    "
                                >
                                    <span
                                        class="
                                            loading loading-spinner
                                            loading-xs text-primary
                                        "
                                    ></span>
                                </div>
                            </div>

                            @if($selectedSize)
                                <div
                                    aria-live="polite"
                                    class="
                                        mt-2.5 overflow-hidden
                                        rounded-xl
                                        border border-primary/15
                                        bg-primary/[0.045]
                                    "
                                >
                                    <div
                                        class="
                                            flex items-center
                                            justify-between gap-3
                                            border-b border-primary/10
                                            px-3.5 py-2
                                        "
                                    >
                                        <div
                                            class="
                                                flex min-w-0
                                                items-center gap-2
                                            "
                                        >
                                            <div
                                                class="
                                                    flex size-7 shrink-0
                                                    items-center justify-center
                                                    rounded-lg
                                                    bg-primary/10 text-primary
                                                "
                                            >
                                                <x-icon
                                                    name="lucide.server"
                                                    class="!size-3.5"
                                                />
                                            </div>

                                            <span
                                                dir="ltr"
                                                class="
                                                    technical-value
                                                    truncate
                                                    text-xs font-semibold
                                                    text-base-content
                                                "
                                            >
                                                {{ $selectedSize['name'] }}
                                            </span>
                                        </div>

                                        <span
                                            class="
                                                inline-flex shrink-0
                                                items-center gap-1
                                                text-[10px] font-medium
                                                text-primary
                                            "
                                        >
                                            <x-icon
                                                name="lucide.circle-check"
                                                class="!size-3.5"
                                            />

                                            انتخاب‌شده
                                        </span>
                                    </div>

                                    <div
                                        class="
                                            grid grid-cols-3
                                            divide-x divide-x-reverse
                                            divide-primary/10
                                        "
                                    >
                                        <div
                                            class="
                                                flex flex-col
                                                items-center justify-center
                                                gap-1 px-2 py-2.5
                                                text-center
                                                sm:flex-row sm:gap-2
                                            "
                                        >
                                            <x-icon
                                                name="lucide.cpu"
                                                class="
                                                    !size-4
                                                    text-primary/70
                                                "
                                            />

                                            <div>
                                                <div
                                                    dir="ltr"
                                                    class="
                                                        technical-value
                                                        text-xs font-semibold
                                                        text-base-content
                                                    "
                                                >
                                                    {{ $selectedSize['vcpu'] }}
                                                </div>
                                                <div
                                                    class="
                                                        mt-0.5 text-[9px]
                                                        text-base-content/40
                                                    "
                                                >
                                                    vCPU
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="
                                                flex flex-col
                                                items-center justify-center
                                                gap-1 px-2 py-2.5
                                                text-center
                                                sm:flex-row sm:gap-2
                                            "
                                        >
                                            <x-icon
                                                name="lucide.memory-stick"
                                                class="
                                                    !size-4
                                                    text-primary/70
                                                "
                                            />

                                            <div>
                                                <div
                                                    dir="ltr"
                                                    class="
                                                        technical-value
                                                        text-xs font-semibold
                                                        text-base-content
                                                    "
                                                >
                                                    {{ $selectedSize['memory_label'] }}
                                                </div>
                                                <div
                                                    class="
                                                        mt-0.5 text-[9px]
                                                        text-base-content/40
                                                    "
                                                >
                                                    RAM
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="
                                                flex flex-col
                                                items-center justify-center
                                                gap-1 px-2 py-2.5
                                                text-center
                                                sm:flex-row sm:gap-2
                                            "
                                        >
                                            <x-icon
                                                name="lucide.hard-drive"
                                                class="
                                                    !size-4
                                                    text-primary/70
                                                "
                                            />

                                            <div>
                                                <div
                                                    dir="ltr"
                                                    class="
                                                        technical-value
                                                        text-xs font-semibold
                                                        text-base-content
                                                    "
                                                >
                                                    {{ $selectedSize['disk_gib'] }} GB
                                                </div>
                                                <div
                                                    class="
                                                        mt-0.5 text-[9px]
                                                        text-base-content/40
                                                    "
                                                >
                                                    Disk
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Operating system and disk --}}
                    <div
                        class="
                            grid gap-3 p-4
                            lg:grid-cols-[140px_minmax(0,1fr)]
                            lg:items-center
                        "
                    >
                        <div>
                            <div
                                class="
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                سیستم
                            </div>

                            <div
                                class="
                                    mt-0.5 text-xs
                                    text-base-content/40
                                "
                            >
                                سیستم‌عامل و دیسک
                            </div>
                        </div>

                        <div
                            class="
                                grid gap-2.5
                                sm:grid-cols-[minmax(0,1fr)_180px]
                                sm:items-start
                            "
                        >
                            <div class="min-w-0">
                                <div
                                    wire:loading.class="opacity-60"
                                    wire:target="imageId"
                                    class="
                                        grid grid-cols-2 gap-1.5
                                        transition-opacity duration-150
                                        sm:grid-cols-4
                                    "
                                >
                                    @foreach($images as $image)
                                        <label
                                            wire:key="os-{{ $image['id'] }}"
                                            class="
                                                relative min-w-0
                                                cursor-pointer
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="server_image"
                                                value="{{ $image['id'] }}"
                                                wire:model.live="imageId"
                                                class="peer sr-only"
                                            />

                                            <span
                                                class="
                                                    flex h-11 w-full min-w-0
                                                    items-center justify-center
                                                    rounded-lg
                                                    border border-base-300
                                                    bg-base-100 px-2
                                                    text-center
                                                    text-[11px] font-medium
                                                    text-base-content/55
                                                    transition-all duration-150
                                                    hover:border-primary/30
                                                    hover:bg-primary/[0.04]
                                                    hover:text-base-content
                                                    peer-checked:border-primary
                                                    peer-checked:bg-primary/10
                                                    peer-checked:font-semibold
                                                    peer-checked:text-primary
                                                    peer-focus-visible:ring-2
                                                    peer-focus-visible:ring-primary/20
                                                "
                                            >
                                                <span
                                                    dir="ltr"
                                                    class="
                                                        technical-value
                                                        truncate
                                                    "
                                                >
                                                    {{ $image['name'] }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div
                                    wire:loading
                                    wire:target="imageId"
                                    class="
                                        mt-1.5 flex
                                        items-center gap-1.5
                                        text-[10px]
                                        text-base-content/45
                                    "
                                >
                                    <span
                                        class="
                                            loading loading-spinner
                                            loading-xs text-primary
                                        "
                                    ></span>

                                    در حال اعمال سیستم‌عامل
                                </div>
                            </div>

                            @if($customDiskEnabled)
                                <div
                                    class="
                                        flex h-11 items-center
                                        justify-between
                                        rounded-xl
                                        border border-base-300
                                        bg-base-100 px-1
                                    "
                                >
                                    <x-button
                                        icon="lucide.minus"
                                        wire:click="decreaseDisk"
                                        wire:target="decreaseDisk,increaseDisk"
                                        wire:loading.attr="disabled"
                                        :disabled="
                                            $selectedDiskGiB
                                            <= $minimumDiskGiB
                                        "
                                        class="
                                            btn-ghost btn-square btn-sm
                                            h-9 min-h-9
                                            cursor-pointer rounded-lg
                                            hover:bg-primary/10
                                            hover:text-primary
                                        "
                                    />

                                    <div
                                        class="
                                            flex min-w-20
                                            items-center justify-center
                                        "
                                    >
                                        <div
                                            wire:loading.remove
                                            wire:target="decreaseDisk,increaseDisk"
                                            dir="ltr"
                                            class="
                                                technical-value
                                                text-center
                                                text-sm font-semibold
                                                text-base-content
                                            "
                                        >
                                            {{ $selectedDiskGiB }}
                                            <span
                                                class="
                                                    text-[10px]
                                                    font-normal
                                                    text-base-content/40
                                                "
                                            >
                                                GB
                                            </span>
                                        </div>

                                        <span
                                            wire:loading
                                            wire:target="decreaseDisk,increaseDisk"
                                            class="
                                                loading loading-spinner
                                                loading-xs text-primary
                                            "
                                        ></span>
                                    </div>

                                    <x-button
                                        icon="lucide.plus"
                                        wire:click="increaseDisk"
                                        wire:target="decreaseDisk,increaseDisk"
                                        wire:loading.attr="disabled"
                                        class="
                                            btn-ghost btn-square btn-sm
                                            h-9 min-h-9
                                            cursor-pointer rounded-lg
                                            hover:bg-primary/10
                                            hover:text-primary
                                        "
                                    />
                                </div>
                            @else
                                <div
                                    class="
                                        flex h-11 items-center
                                        justify-between gap-3
                                        rounded-xl
                                        border border-base-300
                                        bg-base-200/40 px-3
                                    "
                                >
                                    <span
                                        class="
                                            text-[11px]
                                            text-base-content/40
                                        "
                                    >
                                        دیسک
                                    </span>

                                    <span
                                        dir="ltr"
                                        class="
                                            technical-value
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        {{ $selectedDiskGiB }} GB
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                @if($catalogError)
                    <div
                        class="
                            mt-3 flex items-start gap-2
                            rounded-xl
                            border border-warning/20
                            bg-warning/5
                            px-3 py-2.5
                            text-xs leading-5
                            text-base-content/55
                        "
                    >
                        <x-icon
                            name="lucide.triangle-alert"
                            class="
                                mt-0.5 !size-3.5
                                shrink-0 text-warning
                            "
                        />

                        <span>{{ $catalogError }}</span>
                    </div>
                @endif
            </main>

            {{-- Desktop order summary --}}
            <aside
                data-buy-desktop-summary
                class="hidden md:block"
            >
                <div
                    class="
                        sticky top-5 overflow-hidden
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                    "
                >
                    <div
                        class="
                            flex items-center
                            justify-between gap-3
                            border-b border-info/15
                            bg-info/5 px-4 py-3.5
                        "
                    >
                        <div>
                            <div
                                class="
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                سفارش شما
                            </div>

                            <div
                                class="
                                    mt-0.5 text-[11px]
                                    text-base-content/35
                                "
                            >
                                {{ $providerLabel }}
                            </div>
                        </div>

                        <x-icon
                            name="lucide.receipt-text"
                            class="
                                !size-4.5
                                text-base-content/35
                            "
                        />
                    </div>

                    <div class="p-4">
                        <dl class="space-y-2.5 text-xs">
                            <div
                                class="
                                    flex items-center
                                    justify-between gap-3
                                "
                            >
                                <dt class="text-base-content/35">
                                    موقعیت
                                </dt>
                                <dd
                                    class="
                                        truncate font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $selectedRegion
                                        ? $this->regionDisplayName($selectedRegion)
                                        : '—'
                                    }}
                                </dd>
                            </div>

                            <div
                                class="
                                    flex items-center
                                    justify-between gap-3
                                "
                            >
                                <dt class="text-base-content/35">
                                    پلن
                                </dt>
                                <dd
                                    dir="ltr"
                                    class="
                                        technical-value
                                        truncate font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $selectedSize['name'] ?? '—' }}
                                </dd>
                            </div>

                            <div
                                class="
                                    flex items-center
                                    justify-between gap-3
                                "
                            >
                                <dt class="text-base-content/35">
                                    سیستم
                                </dt>
                                <dd
                                    dir="ltr"
                                    class="
                                        technical-value
                                        truncate font-medium
                                        text-base-content
                                    "
                                >
                                    @if($selectedImage)
                                        {{ $selectedImage['distribution'] }}
                                        {{ $selectedImage['version'] }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>

                            <div
                                class="
                                    flex items-center
                                    justify-between gap-3
                                "
                            >
                                <dt class="text-base-content/35">
                                    دیسک
                                </dt>
                                <dd
                                    dir="ltr"
                                    class="
                                        technical-value font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $selectedDiskGiB }} GB
                                </dd>
                            </div>

                            <div
                                class="
                                    flex items-center
                                    justify-between gap-3
                                "
                            >
                                <dt class="text-base-content/35">
                                    دوره
                                </dt>
                                <dd
                                    class="
                                        font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $selectedPeriod['label'] ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="my-4 border-t border-base-300"></div>

                        @if($quoteError)
                            <div
                                class="
                                    mb-3 flex items-center
                                    justify-between gap-2
                                    rounded-lg
                                    bg-warning/5
                                    px-2.5 py-2
                                    text-[11px] leading-5
                                    text-warning
                                "
                            >
                                <span class="min-w-0">
                                    {{ $quoteError }}
                                </span>

                                <button
                                    type="button"
                                    wire:click="retryQuote"
                                    wire:target="retryQuote"
                                    class="
                                        shrink-0 font-medium
                                        underline-offset-2
                                        hover:underline
                                    "
                                >
                                    تلاش دوباره
                                </button>
                            </div>
                        @endif

                        <div>
                            <div
                                class="
                                    text-[11px]
                                    text-base-content/35
                                "
                            >
                                مبلغ نهایی
                            </div>

                            <div
                                wire:loading.remove
                                wire:target="selectRegionGroup,selectRegion,selectSize,selectPeriod,decreaseDisk,increaseDisk,retryQuote"
                            >
                                @if($quote !== [])
                                    <div
                                        class="
                                            mt-1 flex
                                            items-baseline gap-1.5
                                            text-base-content
                                        "
                                    >
                                        <span
                                            dir="ltr"
                                            class="
                                                technical-value
                                                text-xl font-semibold
                                                tracking-tight
                                            "
                                        >
                                            {{ $this->formatToman(
                                                (int) $quote['final_amount']
                                            ) }}
                                        </span>

                                        <span
                                            class="
                                                text-xs font-normal
                                                text-base-content/40
                                            "
                                        >
                                            تومان
                                        </span>
                                    </div>
                                @else
                                    <div
                                        class="
                                            mt-1 text-sm
                                            text-base-content/35
                                        "
                                    >
                                        —
                                    </div>
                                @endif
                            </div>

                            <div
                                wire:loading
                                wire:target="selectRegionGroup,selectRegion,selectSize,selectPeriod,decreaseDisk,increaseDisk,retryQuote"
                                class="
                                    mt-1.5 flex
                                    items-center gap-2
                                    text-xs
                                    text-base-content/35
                                "
                            >
                                <span
                                    class="
                                        loading loading-spinner
                                        loading-xs text-primary
                                    "
                                ></span>
                                به‌روزرسانی قیمت
                            </div>
                        </div>

                        <x-button
                            label="پرداخت و ساخت"
                            icon="lucide.credit-card"
                            wire:click="purchase"
                            wire:target="purchase"
                            spinner
                            :disabled="
                                $quote === []
                                || $catalogError !== null
                                || $quoteError !== null
                            "
                            class="
                                btn-primary btn-sm
                                mt-4 w-full
                                rounded-xl
                            "
                        />

                        <div
                            class="
                                mt-2.5 text-center
                                text-[10px] leading-4
                                text-base-content/30
                            "
                        >
                            اعتبار قیمت:
                            {{ $quoteTtlMinutes }}
                            دقیقه پس از ثبت سفارش
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        @include('livewire.servers.partials.buy-mobile-cta')
    @endif
</div>
