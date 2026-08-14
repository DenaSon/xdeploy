<x-servers.workspace
    :server="$server"
    wire:key="server-renewal-{{ $server->getKey() }}"
>
    <div
        x-data="{ mobileSummaryOpen: false }"
        @keydown.escape.window="mobileSummaryOpen = false"
        wire:init="loadQuote"
        class="pb-28 xl:pb-0"
    >
        {{-- Page heading --}}
        <header
            class="
                mb-6

                flex flex-col gap-4

                sm:flex-row
                sm:items-center
                sm:justify-between
            "
        >
            <div
                class="
                    flex min-w-0
                    items-center gap-3
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
                        name="lucide.calendar-plus"
                        class="!size-5 stroke-[1.8]"
                    />
                </span>

                <div class="min-w-0">
                    <h1
                        class="
                            text-xl
                            font-semibold
                            tracking-tight
                            text-base-content

                            sm:text-2xl
                        "
                    >
                        تمدید سرویس
                    </h1>

                    <p
                        class="
                            mt-1

                            text-xs
                            leading-6
                            text-base-content/45

                            sm:text-sm
                        "
                    >
                        مدت اعتبار این VPS را بدون تغییر در منابع
                        یا سیستم‌عامل افزایش دهید.
                    </p>
                </div>
            </div>


            <x-button
                label="بازگشت به سرور"
                icon="lucide.arrow-right"
                :link="route('panel.servers.dashboard', $server)"
                wire:navigate
                class="
                    btn-ghost
                    btn-sm

                    self-start
                    rounded-xl

                    text-base-content/50

                    hover:bg-base-200
                    hover:text-base-content

                    sm:self-auto
                "
            />
        </header>


        {{-- Payment feedback --}}
        @if($paymentResult === 'success')
            <div
                role="status"
                class="
                    mb-5

                    flex items-start gap-3

                    rounded-2xl

                    border border-success/20
                    bg-success/[0.05]

                    px-4 py-3.5
                "
            >
                <span
                    class="
                        flex size-9 shrink-0
                        items-center justify-center

                        rounded-xl
                        bg-success/10
                        text-success
                    "
                >
                    <x-icon
                        name="lucide.circle-check"
                        class="!size-4.5 stroke-[1.8]"
                    />
                </span>

                <div>
                    <div
                        class="
                            text-sm font-semibold
                            text-success
                        "
                    >
                        تمدید با موفقیت انجام شد
                    </div>

                    <p
                        class="
                            mt-1

                            text-xs
                            leading-6
                            text-base-content/50
                        "
                    >
                        تاریخ پایان سرویس به‌روزرسانی شد و VPS
                        بدون وقفه فعال باقی می‌ماند.
                    </p>
                </div>
            </div>

        @elseif($paymentResult === 'cancelled')
            <div
                role="status"
                class="
                    mb-5

                    flex items-start gap-3

                    rounded-2xl

                    border border-warning/20
                    bg-warning/[0.045]

                    px-4 py-3.5
                "
            >
                <span
                    class="
                        flex size-9 shrink-0
                        items-center justify-center

                        rounded-xl
                        bg-warning/10
                        text-warning
                    "
                >
                    <x-icon
                        name="lucide.circle-alert"
                        class="!size-4.5 stroke-[1.8]"
                    />
                </span>

                <div>
                    <div
                        class="
                            text-sm font-semibold
                            text-base-content
                        "
                    >
                        پرداخت تکمیل نشد
                    </div>

                    <p
                        class="
                            mt-1

                            text-xs
                            leading-6
                            text-base-content/50
                        "
                    >
                        تا پیش از پایان سرویس می‌توانید دوباره
                        فرآیند تمدید و پرداخت را آغاز کنید.
                    </p>
                </div>
            </div>
        @endif


        @if(! $canRenew)
            {{-- Renewal unavailable --}}
            <section
                class="
                    relative
                    overflow-hidden

                    rounded-2xl

                    border border-warning/20
                    bg-warning/[0.045]

                    px-5 py-7
                "
            >
                <div
                    aria-hidden="true"
                    class="
                        pointer-events-none

                        absolute
                        -end-16 -top-20

                        size-48

                        rounded-full
                        bg-warning/[0.07]
                        blur-3xl
                    "
                ></div>

                <div
                    class="
                        relative

                        flex items-start gap-3
                    "
                >
                    <span
                        class="
                            flex size-10 shrink-0
                            items-center justify-center

                            rounded-xl
                            bg-warning/10
                            text-warning
                        "
                    >
                        <x-icon
                            name="lucide.clock-alert"
                            class="!size-5 stroke-[1.8]"
                        />
                    </span>

                    <div>
                        <h2
                            class="
                                text-sm font-semibold
                                text-base-content

                                sm:text-base
                            "
                        >
                            این سرویس در حال حاضر قابل تمدید نیست
                        </h2>

                        <p
                            class="
                                mt-1
                                max-w-2xl

                                text-xs
                                leading-6
                                text-base-content/50

                                sm:text-sm
                            "
                        >
                            تمدید فقط برای VPS ابری فعال و پیش از
                            آغاز فرآیند پایان سرویس امکان‌پذیر است.
                        </p>
                    </div>
                </div>
            </section>

        @else
            <div
                class="
                    grid gap-5

                    xl:grid-cols-[minmax(0,1fr)_340px]
                    xl:items-start
                "
            >
                <main class="min-w-0 space-y-5">

                    {{-- Current service --}}
                    <section
                        @class([
                            '
                                overflow-hidden

                                rounded-2xl

                                border
                                bg-base-100

                                shadow-sm
                                shadow-base-content/[0.015]
                            ',
                            'border-warning/25' => $isExpiringSoon,
                            'border-base-300/80' => ! $isExpiringSoon,
                        ])
                    >
                        {{-- Card heading --}}
                        <div
                            @class([
                                '
                                    flex
                                    items-center justify-between
                                    gap-3

                                    border-b

                                    px-4 py-3.5

                                    sm:px-5
                                ',
                                '
                                    border-warning/15
                                    bg-warning/[0.04]
                                ' => $isExpiringSoon,
                                '
                                    border-base-300/70
                                    bg-base-200/25
                                ' => ! $isExpiringSoon,
                            ])
                        >
                            <div>
                                <h2
                                    class="
                                        text-sm font-semibold
                                        text-base-content
                                    "
                                >
                                    سرویس فعلی
                                </h2>

                                <p
                                    class="
                                        mt-0.5
                                        text-[11px]
                                        text-base-content/40
                                    "
                                >
                                    مشخصات VPS در فرآیند تمدید تغییر نمی‌کند.
                                </p>
                            </div>


                            @if($isExpiringSoon)
                                <span
                                    class="
                                        inline-flex
                                        shrink-0
                                        items-center gap-1.5

                                        rounded-full

                                        border border-warning/20
                                        bg-warning/10

                                        px-2.5 py-1

                                        text-[10px]
                                        font-medium
                                        text-warning
                                    "
                                >
                                    <x-icon
                                        name="lucide.clock-alert"
                                        class="!size-3.5 stroke-[1.8]"
                                    />

                                    نزدیک انقضا
                                </span>
                            @endif
                        </div>


                        {{-- Server specs --}}
                        <div
                            class="
                                grid gap-px

                                bg-base-300/70

                                sm:grid-cols-3
                            "
                        >
                            <div
                                class="
                                    bg-base-100
                                    px-4 py-3.5
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/35
                                    "
                                >
                                    موقعیت
                                </div>

                                <div
                                    class="
                                        mt-1
                                        truncate

                                        text-xs font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $regionLabel }}
                                </div>
                            </div>


                            <div
                                class="
                                    bg-base-100
                                    px-4 py-3.5
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/35
                                    "
                                >
                                    سیستم‌عامل
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                        technical-value

                                        mt-1
                                        truncate

                                        text-xs font-medium
                                        text-base-content
                                    "
                                >
                                    @if($sourceOrder)
                                        {{ $sourceOrder->image_distribution }}
                                        {{ $sourceOrder->image_version }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>


                            <div
                                class="
                                    bg-base-100
                                    px-4 py-3.5
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/35
                                    "
                                >
                                    فضای دیسک
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                        technical-value

                                        mt-1

                                        text-xs font-medium
                                        text-base-content
                                    "
                                >
                                    {{ $sourceOrder?->selected_disk_gib ?? '—' }} GB
                                </div>
                            </div>
                        </div>


                        {{-- Expiration --}}
                        @if($currentExpiresAt)
                            <div
                                wire:ignore
                                x-data="{
                                    endAt: {{ $currentExpiresAt->getTimestamp() * 1000 }},
                                    now: Date.now(),
                                    timer: null,

                                    init() {
                                        this.timer = setInterval(() => {
                                            this.now = Date.now()
                                        }, 1000)
                                    },

                                    destroy() {
                                        clearInterval(this.timer)
                                    },

                                    get totalSeconds() {
                                        return Math.max(
                                            0,
                                            Math.floor((this.endAt - this.now) / 1000)
                                        )
                                    },

                                    get days() {
                                        return Math.floor(this.totalSeconds / 86400)
                                    },

                                    get hours() {
                                        return Math.floor((this.totalSeconds % 86400) / 3600)
                                    },

                                    get minutes() {
                                        return Math.floor((this.totalSeconds % 3600) / 60)
                                    },

                                    get seconds() {
                                        return this.totalSeconds % 60
                                    }
                                }"
                                class="
                                    border-t
                                    border-base-300/70

                                    px-4 py-4

                                    sm:px-5
                                "
                            >
                                <div
                                    class="
                                        flex
                                        flex-col gap-4

                                        sm:flex-row
                                        sm:items-center
                                        sm:justify-between
                                    "
                                >
                                    {{-- Expiration date --}}
                                    <div>
                                        <div
                                            class="
                                                text-[10px]
                                                text-base-content/35
                                            "
                                        >
                                            پایان سرویس
                                        </div>

                                        <div
                                            class="
                                                mt-1

                                                flex flex-wrap
                                                items-center gap-2
                                            "
                                        >
                                            <span
                                                dir="ltr"
                                                class="
                                                    technical-value

                                                    text-xs font-medium
                                                    text-base-content
                                                "
                                            >
                                                {{ \App\Support\Date\JalaliDateFormatter::dateTime(
                                                    $currentExpiresAt,
                                                    ' - '
                                                ) }}
                                            </span>

                                            @if($isExpiringSoon)
                                                <span
                                                    class="
                                                        rounded-full
                                                        bg-warning/10

                                                        px-2 py-0.5

                                                        text-[9px]
                                                        font-medium
                                                        text-warning
                                                    "
                                                >
                                                    نزدیک انقضا
                                                </span>
                                            @endif
                                        </div>
                                    </div>


                                    {{-- Countdown --}}
                                    <div
                                        class="
                                            flex
                                            items-center gap-2
                                        "
                                    >
                                        {{-- Days --}}
                                        <div
                                            class="
                                                min-w-14

                                                rounded-xl
                                                bg-base-200/55

                                                px-2.5 py-2

                                                text-center
                                            "
                                        >
                                            <div
                                                dir="ltr"
                                                class="
                                                    font-mono
                                                    text-base
                                                    font-semibold
                                                    text-base-content
                                                "
                                                x-text="days"
                                            ></div>

                                            <div
                                                class="
                                                    mt-0.5
                                                    text-[9px]
                                                    text-base-content/35
                                                "
                                            >
                                                روز
                                            </div>
                                        </div>


                                        {{-- Hours --}}
                                        <div
                                            class="
                                                min-w-14

                                                rounded-xl
                                                bg-base-200/55

                                                px-2.5 py-2

                                                text-center
                                            "
                                        >
                                            <div
                                                dir="ltr"
                                                class="
                                                    font-mono
                                                    text-base
                                                    font-semibold
                                                    text-base-content
                                                "
                                                x-text="String(hours).padStart(2, '0')"
                                            ></div>

                                            <div
                                                class="
                                                    mt-0.5
                                                    text-[9px]
                                                    text-base-content/35
                                                "
                                            >
                                                ساعت
                                            </div>
                                        </div>


                                        {{-- Minutes --}}
                                        <div
                                            class="
                                                min-w-14

                                                rounded-xl
                                                bg-base-200/55

                                                px-2.5 py-2

                                                text-center
                                            "
                                        >
                                            <div
                                                dir="ltr"
                                                class="
                                                    font-mono
                                                    text-base
                                                    font-semibold
                                                    text-base-content
                                                "
                                                x-text="String(minutes).padStart(2, '0')"
                                            ></div>

                                            <div
                                                class="
                                                    mt-0.5
                                                    text-[9px]
                                                    text-base-content/35
                                                "
                                            >
                                                دقیقه
                                            </div>
                                        </div>


                                        {{-- Seconds --}}
                                        <div
                                            class="
                                                hidden
                                                min-w-14

                                                rounded-xl
                                                bg-base-200/55

                                                px-2.5 py-2

                                                text-center

                                                sm:block
                                            "
                                        >
                                            <div
                                                dir="ltr"
                                                class="
                                                    font-mono
                                                    text-base
                                                    font-semibold
                                                    text-base-content/70
                                                "
                                                x-text="String(seconds).padStart(2, '0')"
                                            ></div>

                                            <div
                                                class="
                                                    mt-0.5
                                                    text-[9px]
                                                    text-base-content/35
                                                "
                                            >
                                                ثانیه
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </section>


                    {{-- Renewal period --}}
                    <section
                        class="
                            rounded-2xl

                            border border-base-300/80
                            bg-base-100

                            p-4

                            shadow-sm
                            shadow-base-content/[0.015]

                            sm:p-5
                        "
                    >
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3
                            "
                        >
                            <div>
                                <h2
                                    class="
                                        text-sm font-semibold
                                        text-base-content

                                        sm:text-base
                                    "
                                >
                                    دوره تمدید
                                </h2>

                                <p
                                    class="
                                        mt-0.5

                                        text-[11px]
                                        text-base-content/40
                                    "
                                >
                                    مدت موردنظر برای تمدید سرویس را انتخاب کنید.
                                </p>
                            </div>


                            <span
                                wire:loading
                                wire:target="loadQuote,selectPeriod"
                                class="
                                    loading
                                    loading-spinner
                                    loading-sm
                                    text-primary
                                "
                            ></span>
                        </div>


                        {{-- Period options --}}
                        <div
                            class="
                                mt-5

                                grid grid-cols-1
                                gap-3

                                sm:grid-cols-3
                            "
                        >
                            @foreach($periods as $periodOption)
                                <button
                                    type="button"
                                    wire:key="renew-period-{{ $periodOption['id'] }}"
                                    wire:click="selectPeriod('{{ $periodOption['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="selectPeriod"
                                    @class([
                                        '
                                            group
                                            relative

                                            flex min-h-28
                                            cursor-pointer
                                            flex-col
                                            items-start
                                            justify-between

                                            overflow-hidden

                                            rounded-2xl

                                            border

                                            p-4

                                            text-right

                                            transition-all
                                            duration-200
                                        ',
                                        '
                                            border-primary/40
                                            bg-primary/[0.045]

                                            ring-2
                                            ring-primary/10

                                            shadow-sm
                                            shadow-primary/[0.035]
                                        ' => $period === $periodOption['id'],
                                        '
                                            border-base-300/80
                                            bg-base-100

                                            hover:-translate-y-px
                                            hover:border-primary/25
                                            hover:bg-base-200/20

                                            hover:shadow-md
                                            hover:shadow-base-content/[0.02]
                                        ' => $period !== $periodOption['id'],
                                    ])
                                >
                                    @if($period === $periodOption['id'])
                                        <div
                                            aria-hidden="true"
                                            class="
                                                pointer-events-none

                                                absolute
                                                -end-10 -top-12

                                                size-28

                                                rounded-full
                                                bg-primary/[0.08]
                                                blur-2xl
                                            "
                                        ></div>
                                    @endif


                                    <div
                                        class="
                                            relative

                                            flex w-full
                                            items-start
                                            justify-between
                                            gap-2
                                        "
                                    >
                                        <div>
                                            <div
                                                @class([
                                                    '
                                                        text-sm
                                                        font-semibold
                                                    ',
                                                    'text-primary' =>
                                                        $period === $periodOption['id'],
                                                    'text-base-content' =>
                                                        $period !== $periodOption['id'],
                                                ])
                                            >
                                                {{ $periodOption['label'] }}
                                            </div>

                                            <div
                                                class="
                                                    mt-1

                                                    text-[11px]
                                                    leading-5
                                                    text-base-content/40
                                                "
                                            >
                                                {{ $periodOption['hint'] }}
                                            </div>
                                        </div>


                                        <span
                                            @class([
                                                '
                                                    flex size-5 shrink-0
                                                    items-center justify-center

                                                    rounded-full

                                                    border

                                                    transition-all
                                                ',
                                                '
                                                    border-primary
                                                    bg-primary
                                                    text-primary-content
                                                ' => $period === $periodOption['id'],
                                                '
                                                    border-base-300
                                                    bg-base-100
                                                ' => $period !== $periodOption['id'],
                                            ])
                                        >
                                            @if($period === $periodOption['id'])
                                                <x-icon
                                                    name="lucide.check"
                                                    class="!size-3 stroke-[2]"
                                                />
                                            @endif
                                        </span>
                                    </div>


                                    @if($periodOption['recommended'])
                                        <span
                                            class="
                                                relative
                                                mt-3

                                                inline-flex
                                                items-center gap-1

                                                rounded-full
                                                bg-primary/[0.08]

                                                px-2 py-0.5

                                                text-[9px]
                                                font-medium
                                                text-primary
                                            "
                                        >
                                            <x-icon
                                                name="lucide.sparkles"
                                                class="!size-3 stroke-[1.8]"
                                            />

                                            پیشنهاد‌شده
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>


                        {{-- Renewal note --}}
                        <div
                            class="
                                mt-4

                                flex items-start gap-2.5

                                rounded-xl

                                border border-info/15
                                bg-info/[0.035]

                                px-3.5 py-3
                            "
                        >
                            <x-icon
                                name="lucide.info"
                                class="
                                    mt-0.5
                                    !size-3.5
                                    shrink-0
                                    text-info
                                "
                            />

                            <p
                                class="
                                    text-[11px]
                                    leading-5
                                    text-base-content/45
                                "
                            >
                                زمان باقی‌مانده فعلی حفظ می‌شود و دوره
                                خریداری‌شده به تاریخ پایان فعلی سرویس اضافه خواهد شد.
                            </p>
                        </div>
                    </section>
                </main>


                {{-- Desktop summary --}}
                <aside class="hidden xl:block">
                    <div
                        class="
                            sticky top-5

                            overflow-hidden
                            rounded-2xl

                            border border-base-300/80
                            bg-base-100

                            shadow-sm
                            shadow-base-content/[0.02]
                        "
                    >
                        {{-- Summary heading --}}
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3

                                border-b border-base-300/70
                                bg-base-200/25

                                px-4 py-3.5
                            "
                        >
                            <div>
                                <div
                                    class="
                                        text-sm font-semibold
                                        text-base-content
                                    "
                                >
                                    خلاصه تمدید
                                </div>

                                <div
                                    class="
                                        mt-0.5
                                        text-[11px]
                                        text-base-content/35
                                    "
                                >
                                    {{ $server->name ?: 'VPS' }}
                                </div>
                            </div>

                            <span
                                class="
                                    flex size-8
                                    items-center justify-center

                                    rounded-lg
                                    bg-base-200
                                    text-base-content/40
                                "
                            >
                                <x-icon
                                    name="lucide.receipt-text"
                                    class="!size-4 stroke-[1.7]"
                                />
                            </span>
                        </div>


                        <div class="p-4">
                            <dl class="space-y-3.5 text-xs">
                                <div
                                    class="
                                        flex
                                        items-center justify-between
                                        gap-3
                                    "
                                >
                                    <dt class="text-base-content/35">
                                        دوره تمدید
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


                                <div
                                    class="
                                        flex
                                        items-start justify-between
                                        gap-3
                                    "
                                >
                                    <dt class="text-base-content/35">
                                        پایان فعلی
                                    </dt>

                                    <dd
                                        dir="ltr"
                                        class="
                                            technical-value

                                            text-[11px]
                                            font-medium
                                            text-base-content
                                        "
                                    >
                                        {{ $currentExpiresAt
                                            ? \App\Support\Date\JalaliDateFormatter::dateTime($currentExpiresAt)
                                            : '—'
                                        }}
                                    </dd>
                                </div>


                                <div
                                    class="
                                        flex
                                        items-start justify-between
                                        gap-3
                                    "
                                >
                                    <dt class="text-base-content/35">
                                        پایان جدید
                                    </dt>

                                    <dd
                                        dir="ltr"
                                        class="
                                            technical-value

                                            text-[11px]
                                            font-semibold
                                            text-success
                                        "
                                    >
                                        {{ $projectedExpiresAt
                                            ? \App\Support\Date\JalaliDateFormatter::dateTime($projectedExpiresAt)
                                            : '—'
                                        }}
                                    </dd>
                                </div>
                            </dl>


                            <div
                                class="
                                    my-4
                                    border-t border-base-300/70
                                "
                            ></div>


                            @if($quoteError)
                                <div
                                    class="
                                        mb-3

                                        rounded-xl
                                        bg-error/[0.055]

                                        px-3 py-2.5

                                        text-[11px]
                                        leading-5
                                        text-error
                                    "
                                >
                                    {{ $quoteError }}
                                </div>
                            @endif


                            {{-- Price --}}
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
                                    wire:target="loadQuote,selectPeriod"
                                >
                                    @if($quote !== [])
                                        <div
                                            class="
                                                mt-1

                                                flex items-baseline
                                                gap-1.5
                                            "
                                        >
                                            <span
                                                dir="ltr"
                                                class="
                                                    text-2xl
                                                    font-semibold
                                                    tracking-tight
                                                    text-base-content
                                                "
                                            >
                                                {{ $this->formatToman(
                                                    (int) $quote['final_amount']
                                                ) }}
                                            </span>

                                            <span
                                                class="
                                                    text-xs
                                                    text-base-content/40
                                                "
                                            >
                                                تومان
                                            </span>
                                        </div>
                                    @else
                                        <div
                                            class="
                                                mt-1
                                                text-sm
                                                text-base-content/35
                                            "
                                        >
                                            —
                                        </div>
                                    @endif
                                </div>


                                <div
                                    wire:loading
                                    wire:target="loadQuote,selectPeriod"
                                    class="
                                        mt-2

                                        flex
                                        items-center gap-2

                                        text-xs
                                        text-base-content/35
                                    "
                                >
                                    <span
                                        class="
                                            loading
                                            loading-spinner
                                            loading-xs
                                            text-primary
                                        "
                                    ></span>

                                    در حال دریافت قیمت
                                </div>
                            </div>


                            {{-- Payment action --}}
                            <x-button
                                label="پرداخت و تمدید"
                                icon="lucide.credit-card"
                                wire:click="renew"
                                wire:target="renew"
                                spinner
                                :disabled="$quote === [] || $quoteError !== null"
                                class="
                                    btn-primary
                                    btn-sm

                                    mt-5
                                    w-full

                                    rounded-xl

                                    font-medium

                                    shadow-sm
                                    shadow-primary/10
                                "
                            />


                            <div
                                class="
                                    mt-2.5

                                    text-center
                                    text-[10px]
                                    leading-4
                                    text-base-content/30
                                "
                            >
                                اعتبار قیمت اعلام‌شده:
                                {{ $quoteTtlMinutes }}
                                دقیقه
                            </div>
                        </div>
                    </div>
                </aside>
            </div>


            {{-- Mobile backdrop --}}
            <div
                x-cloak
                x-show="mobileSummaryOpen"
                x-transition.opacity.duration.150ms
                @click="mobileSummaryOpen = false"
                class="
                    fixed inset-0
                    z-40

                    bg-base-content/15
                    backdrop-blur-[2px]

                    xl:hidden
                "
            ></div>


            {{-- Mobile summary sheet --}}
            <div
                x-cloak
                x-show="mobileSummaryOpen"
                x-transition:enter="
                    transition
                    ease-out
                    duration-200
                "
                x-transition:enter-start="
                    opacity-0
                    translate-y-5
                "
                x-transition:enter-end="
                    opacity-100
                    translate-y-0
                "
                x-transition:leave="
                    transition
                    ease-in
                    duration-150
                "
                x-transition:leave-start="
                    opacity-100
                    translate-y-0
                "
                x-transition:leave-end="
                    opacity-0
                    translate-y-5
                "
                @click.outside="mobileSummaryOpen = false"
                class="
                    fixed
                    inset-x-3 bottom-20
                    z-50

                    overflow-hidden
                    rounded-2xl

                    border border-base-300/80
                    bg-base-100/95

                    shadow-2xl
                    shadow-base-content/10

                    backdrop-blur-xl

                    xl:hidden
                "
            >
                <div
                    class="
                        flex
                        items-center justify-between

                        border-b border-base-300/70

                        px-4 py-3.5
                    "
                >
                    <div>
                        <div
                            class="
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            خلاصه تمدید
                        </div>

                        <div
                            class="
                                mt-0.5
                                text-[10px]
                                text-base-content/35
                            "
                        >
                            {{ $server->name ?: 'VPS' }}
                        </div>
                    </div>


                    <button
                        type="button"
                        @click="mobileSummaryOpen = false"
                        aria-label="بستن خلاصه تمدید"
                        class="
                            btn
                            btn-square
                            btn-ghost
                            btn-sm

                            rounded-xl

                            text-base-content/45
                        "
                    >
                        <x-icon
                            name="lucide.x"
                            class="!size-4 stroke-[1.8]"
                        />
                    </button>
                </div>


                <div class="p-4">
                    <dl
                        class="
                            grid grid-cols-2
                            gap-3

                            text-xs
                        "
                    >
                        <div
                            class="
                                rounded-xl
                                bg-base-200/50

                                p-3
                            "
                        >
                            <dt
                                class="
                                    text-[10px]
                                    text-base-content/35
                                "
                            >
                                دوره
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    font-medium
                                    text-base-content
                                "
                            >
                                {{ $selectedPeriod['label'] ?? '—' }}
                            </dd>
                        </div>


                        <div
                            class="
                                rounded-xl
                                bg-base-200/50

                                p-3
                            "
                        >
                            <dt
                                class="
                                    text-[10px]
                                    text-base-content/35
                                "
                            >
                                پایان جدید
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value

                                    mt-1

                                    text-[10px]
                                    font-semibold
                                    text-success
                                "
                            >
                                {{ $projectedExpiresAt
                                    ? \App\Support\Date\JalaliDateFormatter::dateTime($projectedExpiresAt)
                                    : '—'
                                }}
                            </dd>
                        </div>
                    </dl>


                    @if($quoteError)
                        <div
                            class="
                                mt-3

                                rounded-xl
                                bg-error/[0.055]

                                px-3 py-2.5

                                text-[11px]
                                leading-5
                                text-error
                            "
                        >
                            {{ $quoteError }}
                        </div>
                    @endif
                </div>
            </div>


            {{-- Mobile payment bar --}}
            <div
                class="
                    fixed
                    inset-x-0 bottom-0
                    z-50

                    border-t border-base-300/80
                    bg-base-100/90

                    px-3 py-2.5

                    shadow-[0_-10px_30px_rgba(15,23,42,0.04)]

                    backdrop-blur-xl

                    xl:hidden
                "
            >
                <div
                    class="
                        mx-auto

                        flex max-w-2xl
                        items-center gap-3
                    "
                >
                    <button
                        type="button"
                        @click="mobileSummaryOpen = ! mobileSummaryOpen"
                        class="
                            min-w-0
                            flex-1

                            text-start
                        "
                    >
                        <div
                            class="
                                flex
                                items-center gap-1

                                text-[9px]
                                text-base-content/30
                            "
                        >
                            مبلغ تمدید

                            <x-icon
                                name="lucide.chevron-up"
                                class="
                                    !size-3

                                    transition-transform
                                    duration-200
                                "
                                x-bind:class="mobileSummaryOpen
                                    ? 'rotate-180'
                                    : ''"
                            />
                        </div>


                        <div
                            wire:loading.remove
                            wire:target="loadQuote,selectPeriod"
                        >
                            @if($quote !== [])
                                <div
                                    class="
                                        flex
                                        items-baseline gap-1
                                    "
                                >
                                    <span
                                        dir="ltr"
                                        class="
                                            truncate

                                            text-sm
                                            font-semibold
                                            text-base-content
                                        "
                                    >
                                        {{ $this->formatToman(
                                            (int) $quote['final_amount']
                                        ) }}
                                    </span>

                                    <span
                                        class="
                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        تومان
                                    </span>
                                </div>
                            @else
                                <span
                                    class="
                                        text-xs
                                        text-base-content/35
                                    "
                                >
                                    —
                                </span>
                            @endif
                        </div>


                        <div
                            wire:loading
                            wire:target="loadQuote,selectPeriod"
                            class="
                                flex
                                items-center gap-1.5

                                text-[10px]
                                text-base-content/35
                            "
                        >
                            <span
                                class="
                                    loading
                                    loading-spinner
                                    loading-xs
                                    text-primary
                                "
                            ></span>

                            دریافت قیمت
                        </div>
                    </button>


                    <x-button
                        label="پرداخت و تمدید"
                        icon="lucide.credit-card"
                        wire:click="renew"
                        wire:target="renew"
                        spinner
                        :disabled="$quote === [] || $quoteError !== null"
                        class="
                            btn-primary
                            btn-sm

                            min-w-32

                            rounded-xl
                            font-medium
                        "
                    />
                </div>
            </div>
        @endif
    </div>
</x-servers.workspace>
