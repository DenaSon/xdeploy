<div
    dir="rtl"
    class="space-y-6"
>
    <header
        class="
            flex flex-col gap-4
            sm:flex-row
            sm:items-end
            sm:justify-between
        "
    >
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <div
                    class="
                        flex size-10 shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.cloud"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <h1
                        class="
                            text-2xl font-semibold
                            tracking-tight
                            text-base-content
                        "
                    >
                        خرید سرور جدید
                    </h1>

                    <p
                        class="
                            mt-1
                            text-sm leading-6
                            text-base-content/50
                        "
                    >
                        موقعیت، منابع و سیستم‌عامل را انتخاب کن؛
                        xDeploy ساخت و آماده‌سازی VPS را انجام می‌دهد.
                    </p>
                </div>
            </div>
        </div>

        <x-button
            label="بازگشت به سرورها"
            icon="lucide.arrow-right"
            :link="route('panel.servers.index')"
            wire:navigate
            class="
                btn-ghost
                rounded-xl
                text-base-content/60
            "
        />
    </header>

    @if($catalogError && $regions === [])
        <section
            class="
                rounded-2xl
                border border-error/20
                bg-error/5
                px-5 py-10
                text-center
            "
        >
            <div
                class="
                    mx-auto
                    flex size-12
                    items-center justify-center
                    rounded-xl
                    bg-error/10
                    text-error
                "
            >
                <x-icon
                    name="lucide.cloud-off"
                    class="!size-5"
                />
            </div>

            <h2 class="mt-4 font-semibold text-base-content">
                دریافت اطلاعات Cloud ناموفق بود
            </h2>

            <p class="mx-auto mt-2 max-w-lg text-sm leading-7 text-base-content/55">
                {{ $catalogError }}
            </p>

            <x-button
                label="تلاش دوباره"
                icon="lucide.refresh-cw"
                wire:click="reloadCatalog"
                spinner
                class="btn-primary mt-5 rounded-xl"
            />
        </section>
    @else
        <div
            class="
                grid grid-cols-1 gap-6
                xl:grid-cols-[minmax(0,1fr)_340px]
            "
        >
            <main class="min-w-0 space-y-5">

                {{-- Region --}}
                <section
                    class="
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        p-5
                    "
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="
                                flex size-9 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-base-200
                                text-base-content/55
                            "
                        >
                            <x-icon
                                name="lucide.map-pin"
                                class="!size-4.5"
                            />
                        </div>

                        <div>
                            <h2 class="font-semibold text-base-content">
                                موقعیت سرور
                            </h2>

                            <p class="mt-1 text-sm text-base-content/45">
                                دیتاسنتری را انتخاب کن که سرور در آن ساخته شود.
                            </p>
                        </div>
                    </div>

                    <div
                        class="
                            mt-5
                            grid grid-cols-1 gap-2.5
                            md:grid-cols-2
                            2xl:grid-cols-3
                        "
                    >
                        @foreach($regions as $region)
                            <button
                                type="button"
                                wire:key="region-{{ $region['id'] }}"
                                wire:click="selectRegion('{{ $region['id'] }}')"
                                @class([
                                    '
                                        rounded-xl border
                                        px-4 py-3.5
                                        text-right
                                        transition-colors
                                    ',
                                    'border-primary bg-primary/5' =>
                                        $regionId === $region['id'],
                                    'border-base-300 hover:border-primary/25 hover:bg-base-200/40' =>
                                        $regionId !== $region['id'],
                                ])
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-base-content">
                                            {{ $region['country'] ?? 'Cloud' }}
                                            @if($region['city'])
                                                <span class="text-base-content/35">/</span>
                                                {{ $region['city'] }}
                                            @endif
                                        </div>

                                        <div
                                            dir="ltr"
                                            class="
                                                technical-value
                                                mt-1 truncate
                                                text-left text-xs
                                                text-base-content/40
                                            "
                                        >
                                            {{ $region['data_center'] ?? $region['id'] }}
                                        </div>
                                    </div>

                                    @if($regionId === $region['id'])
                                        <x-icon
                                            name="lucide.circle-check"
                                            class="!size-4.5 shrink-0 text-primary"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- Plan --}}
                <section
                    class="
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        p-5
                    "
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="
                                flex size-9 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-base-200
                                text-base-content/55
                            "
                        >
                            <x-icon
                                name="lucide.cpu"
                                class="!size-4.5"
                            />
                        </div>

                        <div>
                            <h2 class="font-semibold text-base-content">
                                پلن منابع
                            </h2>

                            <p class="mt-1 text-sm text-base-content/45">
                                CPU، RAM و فضای پایه متناسب با نیازت انتخاب کن.
                            </p>
                        </div>
                    </div>

                    @if($sizes !== [])
                        <div
                            class="
                                mt-5
                                grid grid-cols-1 gap-3
                                md:grid-cols-2
                                2xl:grid-cols-3
                            "
                        >
                            @foreach($sizes as $size)
                                <button
                                    type="button"
                                    wire:key="size-{{ $size['id'] }}"
                                    wire:click="selectSize('{{ $size['id'] }}')"
                                    @class([
                                        '
                                            rounded-xl border
                                            p-4 text-right
                                            transition-colors
                                        ',
                                        'border-primary bg-primary/5' =>
                                            $sizeId === $size['id'],
                                        'border-base-300 hover:border-primary/25' =>
                                            $sizeId !== $size['id'],
                                    ])
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-base-content">
                                                {{ $size['name'] }}
                                            </div>

                                            @if($size['category'])
                                                <div class="mt-1 text-xs text-base-content/35">
                                                    {{ $size['category'] }}
                                                </div>
                                            @endif
                                        </div>

                                        @if($sizeId === $size['id'])
                                            <x-icon
                                                name="lucide.circle-check"
                                                class="!size-4.5 shrink-0 text-primary"
                                            />
                                        @endif
                                    </div>

                                    <div
                                        class="
                                            mt-4 grid grid-cols-3
                                            divide-x divide-x-reverse
                                            divide-base-300
                                            rounded-lg
                                            bg-base-200/55
                                            py-2.5
                                            text-center
                                        "
                                    >
                                        <div>
                                            <div
                                                dir="ltr"
                                                class="technical-value text-sm font-semibold"
                                            >
                                                {{ $size['vcpu'] }}
                                            </div>
                                            <div class="mt-0.5 text-[10px] text-base-content/40">
                                                vCPU
                                            </div>
                                        </div>

                                        <div>
                                            <div
                                                dir="ltr"
                                                class="technical-value text-sm font-semibold"
                                            >
                                                {{ $size['memory_label'] }}
                                            </div>
                                            <div class="mt-0.5 text-[10px] text-base-content/40">
                                                RAM
                                            </div>
                                        </div>

                                        <div>
                                            <div
                                                dir="ltr"
                                                class="technical-value text-sm font-semibold"
                                            >
                                                {{ $size['disk_gib'] }} GB
                                            </div>
                                            <div class="mt-0.5 text-[10px] text-base-content/40">
                                                Disk
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 text-sm text-base-content/50">
                            پلنی برای این منطقه در دسترس نیست.
                        </p>
                    @endif
                </section>

                {{-- OS --}}
                <section
                    class="
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        p-5
                    "
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="
                                flex size-9 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-base-200
                                text-base-content/55
                            "
                        >
                            <x-icon
                                name="lucide.monitor-cog"
                                class="!size-4.5"
                            />
                        </div>

                        <div>
                            <h2 class="font-semibold text-base-content">
                                سیستم‌عامل
                            </h2>

                            <p class="mt-1 text-sm text-base-content/45">
                                فقط نسخه‌هایی نمایش داده می‌شوند که xDeploy پشتیبانی می‌کند.
                            </p>
                        </div>
                    </div>

                    @if($images !== [])
                        <div
                            class="
                                mt-5
                                grid grid-cols-1 gap-2.5
                                sm:grid-cols-2
                                lg:grid-cols-3
                            "
                        >
                            @foreach($images as $image)
                                <button
                                    type="button"
                                    wire:key="image-{{ $image['id'] }}"
                                    wire:click="selectImage('{{ $image['id'] }}')"
                                    @class([
                                        '
                                            flex items-center gap-3
                                            rounded-xl border
                                            px-4 py-3
                                            text-right
                                            transition-colors
                                        ',
                                        'border-primary bg-primary/5' =>
                                            $imageId === $image['id'],
                                        'border-base-300 hover:border-primary/25' =>
                                            $imageId !== $image['id'],
                                    ])
                                >
                                    <div
                                        class="
                                            flex size-9 shrink-0
                                            items-center justify-center
                                            rounded-lg
                                            bg-base-200
                                        "
                                    >
                                        <x-icon
                                            name="lucide.terminal"
                                            class="!size-4 text-base-content/55"
                                        />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-base-content">
                                            {{ $image['distribution'] }}
                                        </div>

                                        <div
                                            dir="ltr"
                                            class="
                                                technical-value
                                                mt-0.5 text-left text-xs
                                                text-base-content/45
                                            "
                                        >
                                            {{ $image['version'] }}
                                        </div>
                                    </div>

                                    @if($imageId === $image['id'])
                                        <x-icon
                                            name="lucide.circle-check"
                                            class="!size-4.5 shrink-0 text-primary"
                                        />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Disk + period --}}
                <section
                    class="
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        p-5
                    "
                >
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-icon
                                    name="lucide.hard-drive"
                                    class="!size-4.5 text-base-content/45"
                                />

                                <h2 class="font-semibold text-base-content">
                                    فضای دیسک
                                </h2>
                            </div>

                            <p class="mt-1.5 text-sm text-base-content/45">
                                حداقل این پیکربندی:
                                <span
                                    dir="ltr"
                                    class="technical-value"
                                >
                                    {{ $minimumDiskGiB }} GB
                                </span>
                            </p>

                            <div
                                class="
                                    mt-4
                                    inline-flex items-center gap-2
                                    rounded-xl
                                    border border-base-300
                                    bg-base-200/40
                                    p-1.5
                                "
                            >
                                <x-button
                                    icon="lucide.minus"
                                    wire:click="decreaseDisk"
                                    class="
                                        btn-ghost btn-square btn-sm
                                        rounded-lg
                                    "
                                    :disabled="$selectedDiskGiB <= $minimumDiskGiB"
                                />

                                <div
                                    dir="ltr"
                                    class="
                                        technical-value
                                        min-w-24 text-center
                                        text-sm font-semibold
                                    "
                                >
                                    {{ $selectedDiskGiB }} GB
                                </div>

                                <x-button
                                    icon="lucide.plus"
                                    wire:click="increaseDisk"
                                    class="
                                        btn-ghost btn-square btn-sm
                                        rounded-lg
                                    "
                                />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <x-icon
                                    name="lucide.calendar-clock"
                                    class="!size-4.5 text-base-content/45"
                                />

                                <h2 class="font-semibold text-base-content">
                                    دوره
                                </h2>
                            </div>

                            <p class="mt-1.5 text-sm text-base-content/45">
                                مدت استفاده از سرور را انتخاب کن.
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($periods as $periodOption)
                                    <button
                                        type="button"
                                        wire:key="period-{{ $periodOption['id'] }}"
                                        wire:click="selectPeriod('{{ $periodOption['id'] }}')"
                                        @class([
                                            '
                                                rounded-xl border
                                                px-4 py-2
                                                text-sm font-medium
                                                transition-colors
                                            ',
                                            'border-primary bg-primary text-primary-content' =>
                                                $period === $periodOption['id'],
                                            'border-base-300 hover:border-primary/25' =>
                                                $period !== $periodOption['id'],
                                        ])
                                    >
                                        {{ $periodOption['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                @if($catalogError)
                    <div
                        class="
                            rounded-xl
                            border border-warning/20
                            bg-warning/5
                            px-4 py-3
                            text-sm text-base-content/65
                        "
                    >
                        {{ $catalogError }}
                    </div>
                @endif
            </main>

            {{-- Summary --}}
            <aside class="min-w-0">
                <div
                    class="
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        p-5
                        xl:sticky xl:top-20
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-base-content">
                            خلاصه سفارش
                        </h2>

                        <div
                            wire:loading
                            wire:target="
                                selectRegion,
                                selectSize,
                                selectImage,
                                selectPeriod,
                                increaseDisk,
                                decreaseDisk
                            "
                            class="loading loading-spinner loading-xs text-primary"
                        ></div>
                    </div>

                    <dl class="mt-5 space-y-3.5 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                موقعیت
                            </dt>

                            <dd class="max-w-44 text-left font-medium text-base-content">
                                {{ $selectedRegion['country'] ?? '—' }}
                                @if($selectedRegion['city'] ?? null)
                                    / {{ $selectedRegion['city'] }}
                                @endif
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                پلن
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    max-w-44 truncate
                                    text-left font-medium
                                    text-base-content
                                "
                            >
                                {{ $selectedSize['name'] ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                منابع
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    text-left text-xs
                                    text-base-content/70
                                "
                            >
                                @if($selectedSize)
                                    {{ $selectedSize['vcpu'] }} vCPU
                                    · {{ $selectedSize['memory_label'] }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                سیستم‌عامل
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    text-left font-medium
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

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                دیسک
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    text-left font-medium
                                    text-base-content
                                "
                            >
                                {{ $selectedDiskGiB > 0 ? $selectedDiskGiB . ' GB' : '—' }}
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-base-content/45">
                                دوره
                            </dt>

                            <dd class="text-left font-medium text-base-content">
                                {{ $selectedPeriod['label'] ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="my-5 border-t border-base-300"></div>

                    @if($quoteError)
                        <div
                            class="
                                rounded-xl
                                border border-error/15
                                bg-error/5
                                px-3 py-3
                                text-xs leading-6
                                text-error
                            "
                        >
                            {{ $quoteError }}
                        </div>
                    @elseif($quote !== [])
                        <div>
                            <div class="text-xs text-base-content/45">
                                مبلغ قابل پرداخت
                            </div>

                            <div
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-1.5
                                    text-left
                                    text-2xl font-semibold
                                    tracking-tight
                                    text-base-content
                                "
                            >
                                {{ number_format($quote['final_amount']) }}
                                <span class="text-sm font-normal text-base-content/45">
                                    ریال
                                </span>
                            </div>
                        </div>

                        <p class="mt-2 text-xs leading-5 text-base-content/40">
                            قیمت هنگام ثبت سفارش دوباره به‌صورت authoritative محاسبه می‌شود.
                        </p>
                    @else
                        <div class="text-sm text-base-content/45">
                            برای نمایش قیمت، پیکربندی را کامل کن.
                        </div>
                    @endif

                    <x-button
                        label="پرداخت و ساخت سرور"
                        icon="lucide.credit-card"
                        wire:click="purchase"
                        wire:target="purchase"
                        spinner
                        class="
                            btn-primary
                            mt-5 w-full
                            rounded-xl
                            font-medium
                        "
                        :disabled="
                            $quote === []
                            || $catalogError !== null
                        "
                    />

                    <div
                        class="
                            mt-4
                            flex items-start gap-2
                            text-xs leading-5
                            text-base-content/40
                        "
                    >
                        <x-icon
                            name="lucide.shield-check"
                            class="mt-0.5 !size-3.5 shrink-0"
                        />

                        <span>
                            پس از تأیید پرداخت، ساخت VPS به‌صورت خودکار شروع می‌شود.
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    @endif
</div>
