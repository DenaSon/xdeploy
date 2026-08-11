<div
    dir="rtl"
    class="space-y-4"
    @if($shouldPoll)
        wire:poll.3s="refreshOrder"
    @endif
>
    {{-- Page header --}}
    <header
        class="
            flex flex-col gap-3
            sm:flex-row
            sm:items-center
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
                        border border-primary/15
                        bg-primary/5
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.receipt-text"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="
                                text-xl font-semibold
                                tracking-tight
                                text-base-content
                                sm:text-2xl
                            "
                        >
                            وضعیت سفارش
                        </h1>




                        <span
                            dir="ltr"
                            class="
                                technical-value
                                text-xs
                                text-base-content/35
                            "
                        >
                            #{{ $order->id }}
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-xs leading-6
                            text-base-content/45
                            sm:text-sm
                        "
                    >
                        وضعیت پرداخت و آماده‌سازی VPS را از این صفحه دنبال کنید.
                    </p>
                </div>
            </div>
        </div>

        <x-button
            label="سرورها"
            icon="lucide.arrow-right"
            :link="route('panel.servers.index')"
            wire:navigate
            class="
                btn-ghost btn-sm
                self-start rounded-xl
                text-base-content/55
                sm:self-auto
            "
        />
    </header>

    {{-- Primary status --}}
    <section
        @class([
            '
                rounded-2xl
                border
                p-4
                sm:p-5
            ',
            'border-success/25 bg-success/[0.06]' =>
                $serverReady,

            'border-success/20 bg-success/[0.045]' =>
                ! $serverReady
                && $statusTone === 'success',

            'border-primary/20 bg-primary/[0.045]' =>
                ! $serverReady
                && $statusTone === 'primary',

            'border-info/20 bg-info/[0.045]' =>
                ! $serverReady
                && $statusTone === 'info',

            'border-warning/20 bg-warning/[0.045]' =>
                ! $serverReady
                && $statusTone === 'warning',

            'border-error/20 bg-error/[0.045]' =>
                ! $serverReady
                && $statusTone === 'error',

            'border-base-300 bg-base-100' =>
                ! $serverReady
                && $statusTone === 'neutral',
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
            <div class="flex min-w-0 items-start gap-3">
                <div
                    @class([
                        '
                            flex size-11 shrink-0
                            items-center justify-center
                            rounded-xl
                        ',
                        'bg-success/15 text-success' =>
                            $serverReady,

                        'bg-success/10 text-success' =>
                            ! $serverReady
                            && $statusTone === 'success',

                        'bg-primary/10 text-primary' =>
                            ! $serverReady
                            && $statusTone === 'primary',

                        'bg-info/10 text-info' =>
                            ! $serverReady
                            && $statusTone === 'info',

                        'bg-warning/10 text-warning' =>
                            ! $serverReady
                            && $statusTone === 'warning',

                        'bg-error/10 text-error' =>
                            ! $serverReady
                            && $statusTone === 'error',

                        'bg-base-200 text-base-content/55' =>
                            ! $serverReady
                            && $statusTone === 'neutral',
                    ])
                >
                    <x-icon
                        :name="
                            $serverReady
                                ? 'lucide.circle-check'
                                : $statusMeta['icon']
                        "
                        @class([
                            '!size-5 stroke-[1.8]',
                            'animate-spin' =>
                                ! $serverReady
                                && $isProvisioning,
                        ])
                    />
                </div>

                <div class="min-w-0">
                    @if($serverReady)
                        <h2
                            class="
                                text-base font-semibold
                                text-success
                                sm:text-lg
                            "
                        >
                            سرور آماده استفاده است
                        </h2>

                        <p
                            class="
                                mt-1
                                max-w-2xl
                                text-xs leading-6
                                text-base-content/55
                                sm:text-sm
                            "
                        >
                            ساخت VPS و اتصال SSH با موفقیت تکمیل شده است.
                            اکنون می‌توانید مدیریت سرور را در xDeploy آغاز کنید.
                        </p>
                    @else
                        <h2
                            class="
                                text-base font-semibold
                                text-base-content
                                sm:text-lg
                            "
                        >
                            {{ $statusMeta['label'] }}
                        </h2>

                        <p
                            class="
                                mt-1
                                max-w-2xl
                                text-xs leading-6
                                text-base-content/50
                                sm:text-sm
                            "
                        >
                            {{ $statusMeta['description'] }}
                        </p>
                    @endif
                </div>
            </div>

            @if($serverReady && $server)
                <x-button
                    label="مدیریت سرور"
                    icon="lucide.server-cog"
                    :link="route(
                        'panel.servers.dashboard',
                        $server
                    )"
                    wire:navigate
                    class="
                        btn-success btn-sm
                        shrink-0
                        self-start
                        rounded-xl
                        px-4
                        font-medium
                        text-success-content
                        sm:self-center
                    "
                />

            @elseif(
                $shouldPoll
                && $estimatedReadyAt !== null
            )
                <div
                    x-data="{
                        remaining: 0,
                        deadline: {{ $estimatedReadyAt }} * 1000,
                        timer: null,

                        update() {
                            this.remaining = Math.max(
                                0,
                                Math.ceil(
                                    (this.deadline - Date.now()) / 1000
                                )
                            );
                        },

                        start() {
                            this.update();

                            this.timer = setInterval(
                                () => this.update(),
                                1000
                            );
                        },
                    }"
                    x-init="
                        start();

                        $cleanup(() => {
                            clearInterval(timer);
                        });
                    "
                    class="
                        shrink-0
                        self-start
                        text-center
                        sm:self-center
                    "
                >
                    <template x-if="remaining > 0">
                        <div
                            class="
                                min-w-28
                                rounded-xl
                                border border-primary/15
                                bg-base-100/70
                                px-3 py-2
                            "
                        >
                            <div
                                dir="ltr"
                                class="
                                    inline-flex
                                    items-baseline gap-1.5
                                    text-primary
                                "
                            >
                                <span
                                    class="
                                        countdown
                                        font-mono
                                        text-3xl
                                        font-semibold
                                        tabular-nums
                                    "
                                >
                                    <span
                                        x-text="remaining"
                                        x-bind:style="`--value:${remaining}; --digits:2;`"
                                        x-bind:aria-label="`${remaining} ثانیه`"
                                        aria-live="polite"
                                    ></span>
                                </span>

                                <span
                                    class="
                                        text-[10px]
                                        font-normal
                                        text-base-content/35
                                    "
                                >
                                    ثانیه
                                </span>
                            </div>

                            <div
                                class="
                                    mt-0.5
                                    text-[10px]
                                    text-base-content/35
                                "
                            >
                                زمان تقریبی آماده‌سازی
                            </div>
                        </div>
                    </template>

                    <template x-if="remaining === 0">
                        <div
                            class="
                                inline-flex
                                items-center gap-2
                                rounded-full
                                border border-base-300/80
                                bg-base-100/65
                                px-3 py-1.5
                                text-[11px]
                                text-base-content/45
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

                            در حال تکمیل آماده‌سازی
                        </div>

                    </template>

                </div>

            @elseif($shouldPoll)
                <div
                    class="
                        inline-flex shrink-0
                        items-center gap-2
                        self-start
                        rounded-full
                        border border-base-300/80
                        bg-base-100/65
                        px-3 py-1.5
                        text-[11px]
                        text-base-content/45
                        sm:self-center
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

                    بررسی خودکار وضعیت
                </div>
            @endif
        </div>
    </section>

    {{-- Payment notice --}}
    @if($paymentNotice)
        <div
            role="alert"
            @class([
                '
                    alert alert-soft
                    rounded-xl
                    border
                    text-sm leading-7
                ',
                'alert-warning border-warning/15' =>
                    $paymentNotice['type'] === 'warning',
                'alert-error border-error/15' =>
                    $paymentNotice['type'] === 'error',
                'alert-info border-info/15' =>
                    $paymentNotice['type'] === 'info',
            ])
        >
            <x-icon
                :name="match ($paymentNotice['type']) {
                    'error' => 'lucide.circle-alert',
                    'warning' => 'lucide.triangle-alert',
                    default => 'lucide.info',
                }"
                class="!size-4.5 shrink-0"
            />

            <span>
                {{ $paymentNotice['message'] }}
            </span>
        </div>
    @endif

    {{-- Provisioning progress --}}
    <section
        class="
            rounded-2xl
            border border-base-300
            bg-base-100
            p-4
            sm:p-5
        "
    >
        <div
            class="
                flex items-center
                justify-between gap-3
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
                    مسیر آماده‌سازی
                </h2>

                <p
                    class="
                        mt-0.5
                        text-[11px]
                        text-base-content/35
                    "
                >
                    مراحل آماده‌شدن VPS و اتصال به xDeploy
                </p>
            </div>

            @if($serverReady)
                <span
                    class="
                        inline-flex items-center gap-1.5
                        rounded-full
                        bg-success/10
                        px-2.5 py-1
                        text-[10px]
                        font-medium
                        text-success
                    "
                >
                    <x-icon
                        name="lucide.circle-check"
                        class="!size-3.5"
                    />

                    آماده استفاده
                </span>
            @endif
        </div>

        <div
            class="
                relative mt-5
                grid grid-cols-1 gap-2
                md:grid-cols-4
                md:gap-0
            "
        >
            {{-- Desktop connector --}}
            <div
                aria-hidden="true"
                class="
                    absolute
                    right-[12.5%] left-[12.5%]
                    top-5
                    hidden h-px
                    bg-base-300
                    md:block
                "
            ></div>

            @foreach($steps as $index => $step)
                <div
                    class="
                        relative z-10
                        flex items-center gap-3
                        rounded-xl
                        px-2 py-2
                        md:flex-col
                        md:gap-2
                        md:bg-base-100
                        md:px-3
                        md:py-0
                        md:text-center
                    "
                >
                    <div
                        @class([
                            '
                                flex size-10 shrink-0
                                items-center justify-center
                                rounded-full
                                border
                                transition-colors
                            ',
                            'border-success/25 bg-success text-success-content' =>
                                $step['state'] === 'completed',
                            'border-primary/25 bg-primary text-primary-content ring-4 ring-primary/10' =>
                                $step['state'] === 'current',
                            'border-error/25 bg-error text-error-content' =>
                                $step['state'] === 'failed',
                            'border-base-300 bg-base-100 text-base-content/35' =>
                                $step['state'] === 'upcoming',
                        ])
                    >
                        <x-icon
                            :name="match ($step['state']) {
                                'completed' => 'lucide.check',
                                'failed' => 'lucide.x',
                                default => $step['icon'],
                            }"
                            @class([
                                '!size-4 stroke-[2]',
                                'animate-spin' =>
                                    $step['state'] === 'current'
                                    && $index === 2,
                            ])
                        />
                    </div>

                    <div class="min-w-0 md:mt-1">
                        <div
                            @class([
                                'text-xs font-semibold',
                                'text-success' =>
                                    $step['state'] === 'completed',
                                'text-primary' =>
                                    $step['state'] === 'current',
                                'text-error' =>
                                    $step['state'] === 'failed',
                                'text-base-content/45' =>
                                    $step['state'] === 'upcoming',
                            ])
                        >
                            {{ $step['title'] }}
                        </div>

                        <div
                            class="
                                mt-0.5
                                text-[10px]
                                leading-5
                                text-base-content/35
                            "
                        >
                            {{ $step['description'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Main order content --}}
    <div
        class="
            grid grid-cols-1 gap-4
            lg:grid-cols-[minmax(0,1fr)_300px]
        "
    >
        {{-- Order details --}}
        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-base-300
                bg-base-100
            "
        >
            <div
                class="
                    flex items-center
                    justify-between gap-3
                    border-b border-base-300
                    px-4 py-3.5
                    sm:px-5
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
                        مشخصات سفارش
                    </h2>

                    <p
                        class="
                            mt-0.5
                            text-[11px]
                            text-base-content/35
                        "
                    >
                        پیکربندی ثبت‌شده برای این VPS
                    </p>
                </div>

                <div
                    class="
                        flex size-8
                        items-center justify-center
                        rounded-lg
                        bg-base-200/70
                        text-base-content/35
                    "
                >
                    <x-icon
                        name="lucide.sliders-horizontal"
                        class="!size-4"
                    />
                </div>
            </div>

            <dl>
                {{-- Region + Plan --}}
                <div
                    class="
                        grid
                        sm:grid-cols-2
                        sm:divide-x
                        sm:divide-x-reverse
                        divide-base-300
                    "
                >
                    <div
                        class="
                            flex items-start gap-3
                            border-b border-base-300
                            px-4 py-4
                            sm:border-b-0
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-primary/5
                                text-primary/70
                            "
                        >
                            <x-icon
                                name="lucide.map-pin"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                موقعیت
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                {{ $regionLabel }}
                            </dd>

                            @if($regionLabel !== $order->region_id)
                                <div
                                    dir="ltr"
                                    class="
                                        technical-value
                                        mt-1 text-left
                                        text-[10px]
                                        text-base-content/30
                                    "
                                >
                                    {{ $order->region_id }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div
                        class="
                            flex items-start gap-3
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-base-200/70
                                text-base-content/50
                            "
                        >
                            <x-icon
                                name="lucide.cpu"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                پلن
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-1 text-left
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                {{ $order->size_id }}
                            </dd>
                        </div>
                    </div>
                </div>

                {{-- OS + Disk --}}
                <div
                    class="
                        grid
                        border-t border-base-300
                        sm:grid-cols-2
                        sm:divide-x
                        sm:divide-x-reverse
                        divide-base-300
                    "
                >
                    <div
                        class="
                            flex items-start gap-3
                            border-b border-base-300
                            px-4 py-4
                            sm:border-b-0
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-base-200/70
                                text-base-content/50
                            "
                        >
                            <x-icon
                                name="lucide.monitor-cog"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                سیستم‌عامل
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-1 text-left
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                {{ $order->image_distribution }}
                                {{ $order->image_version }}
                            </dd>
                        </div>
                    </div>

                    <div
                        class="
                            flex items-start gap-3
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-base-200/70
                                text-base-content/50
                            "
                        >
                            <x-icon
                                name="lucide.hard-drive"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                فضای دیسک
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-1 text-left
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                {{ $order->selected_disk_gib }} GB
                            </dd>
                        </div>
                    </div>
                </div>

                {{-- Period + Created at --}}
                <div
                    class="
                        grid
                        border-t border-base-300
                        sm:grid-cols-2
                        sm:divide-x
                        sm:divide-x-reverse
                        divide-base-300
                    "
                >
                    <div
                        class="
                            flex items-start gap-3
                            border-b border-base-300
                            px-4 py-4
                            sm:border-b-0
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-base-200/70
                                text-base-content/50
                            "
                        >
                            <x-icon
                                name="lucide.calendar-range"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                دوره استفاده
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                {{ $periodLabel }}
                            </dd>
                        </div>
                    </div>

                    <div
                        class="
                            flex items-start gap-3
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-base-200/70
                                text-base-content/50
                            "
                        >
                            <x-icon
                                name="lucide.clock-3"
                                class="!size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <dt class="text-[11px] text-base-content/40">
                                زمان ثبت سفارش
                            </dt>

                            <dd
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-1 text-left
                                    text-sm font-medium
                                    text-base-content/70
                                "
                            >
                                {{ $order->created_at?->format(
                                    'Y-m-d H:i'
                                ) }}
                            </dd>
                        </div>
                    </div>
                </div>
            </dl>
        </section>

        {{-- Summary / actions --}}
        <aside
            class="
                rounded-2xl
                border border-base-300
                bg-base-100
                p-4
                sm:p-5
            "
        >
            <div class="text-xs text-base-content/40">
                {{ $amountLabel }}
            </div>

            <div
                class="
                    mt-1.5
                    flex items-baseline gap-1.5
                    text-base-content
                "
            >
                <span
                    dir="ltr"
                    class="
                        text-2xl font-semibold
                        tracking-tight
                    "
                >
                   {{ $amountToman }}
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

            <div class="my-4 border-t border-base-300"></div>

            {{-- Current state --}}
            <div
                class="
                    flex items-center
                    justify-between gap-3
                "
            >
                <span class="text-xs text-base-content/40">
                    وضعیت
                </span>

                <span
                    class="
                        text-xs font-medium
                        text-base-content/75
                    "
                >
                    {{ $statusMeta['label'] }}
                </span>
            </div>

            {{-- Server state --}}
            <div
                class="
                    mt-3
                    flex items-center
                    justify-between gap-3
                "
            >
                <span class="text-xs text-base-content/40">
                    سرور
                </span>

                <span
                    @class([
                        'text-xs font-medium',
                        'text-success' => $serverReady,
                        'text-primary' =>
                            $server instanceof \App\Models\Server
                            && ! $serverReady,
                        'text-base-content/40' =>
                            ! $server instanceof \App\Models\Server,
                    ])
                >
                    @if($serverReady)
                        آماده استفاده
                    @elseif($server)
                        در حال آماده‌سازی
                    @else
                        هنوز ایجاد نشده
                    @endif
                </span>
            </div>

            @if($canPay)
                <x-button
                    label="ادامه پرداخت"
                    icon="lucide.credit-card"
                    wire:click="pay"
                    wire:target="pay"
                    spinner
                    class="
                        btn-primary btn-sm
                        mt-5 w-full
                        rounded-xl
                        font-medium
                    "
                />
            @elseif($canCreateNewOrder)
                <x-button
                    label="ایجاد سفارش جدید"
                    icon="lucide.plus"
                    :link="route('panel.servers.buy')"
                    wire:navigate
                    class="
                        btn-primary btn-sm
                        mt-5 w-full
                        rounded-xl
                        font-medium
                    "
                />
            @endif

            @if($server)
                <div class="my-4 border-t border-base-300"></div>

                <div
                    class="
                        flex items-start
                        gap-2.5
                    "
                >
                    <div
                        class="
                            flex size-8 shrink-0
                            items-center justify-center
                            rounded-lg
                            bg-base-200
                            text-base-content/55
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-4"
                        />
                    </div>

                    <div class="min-w-0">
                        <div
                            class="
                                truncate
                                text-sm font-medium
                                text-base-content
                            "
                        >
                            {{ $server->name }}
                        </div>

                        @if($server->hasConnectionHost())
                            <div
                                dir="ltr"
                                class="
                                    technical-value
                                    mt-0.5 text-left
                                    text-[10px]
                                    text-base-content/40
                                "
                            >
                                {{ $server->host }}:{{ $server->port }}
                            </div>
                        @endif
                    </div>
                </div>

                @if($server->isActive())
                    <div
                        class="
                            mt-3
                            flex items-center gap-2
                            rounded-lg
                            bg-success/5
                            px-2.5 py-2
                            text-[11px]
                            font-medium
                            text-success
                        "
                    >
                        <x-icon
                            name="lucide.circle-check"
                            class="!size-3.5"
                        />

                        اتصال xDeploy آماده است
                    </div>
                @else
                    <p
                        class="
                            mt-3
                            text-[11px] leading-5
                            text-base-content/40
                        "
                    >
                        VPS ساخته شده است؛ اتصال xDeploy پس از آماده‌شدن SSH فعال می‌شود.
                    </p>
                @endif
            @endif

            @if($isFailed)
                <div
                    class="
                        mt-4
                        rounded-xl
                        border border-error/15
                        bg-error/5
                        p-3
                        text-[11px] leading-5
                        text-base-content/55
                    "
                >
                    برای جلوگیری از ایجاد VPS تکراری و هزینه مجدد،
                    Provisioning از این صفحه دوباره اجرا نمی‌شود.
                </div>
            @endif
        </aside>
    </div>
</div>
