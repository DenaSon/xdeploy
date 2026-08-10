<div
    dir="rtl"
    class="space-y-6"
    @if($shouldPoll)
        wire:poll.3s="refreshOrder"
    @endif
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
                        name="lucide.receipt-text"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="
                                text-2xl font-semibold
                                tracking-tight
                                text-base-content
                            "
                        >
                            وضعیت سفارش
                        </h1>

                        <span
                            dir="ltr"
                            class="
                                technical-value
                                text-xs text-base-content/35
                            "
                        >
                            #{{ $order->id }}
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm leading-6
                            text-base-content/50
                        "
                    >
                        وضعیت پرداخت و آماده‌سازی VPS را از این صفحه دنبال کن.
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
                btn-ghost
                rounded-xl
                text-base-content/60
            "
        />
    </header>

    <section
        class="
            rounded-2xl
            border border-base-300
            bg-base-100
            p-5
            sm:p-6
        "
    >
        <div
            class="
                flex flex-col gap-5
                sm:flex-row
                sm:items-start
                sm:justify-between
            "
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="
                        flex size-11 shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-base-200
                        text-base-content/65
                    "
                >
                    <x-icon
                        :name="$statusMeta['icon']"
                        @class([
                            '!size-5 stroke-[1.8]',
                            'animate-spin' =>
                                $order->status ===
                                \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                        ])
                    />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            class="
                                text-lg font-semibold
                                text-base-content
                            "
                        >
                            {{ $statusMeta['label'] }}
                        </h2>

                        <span
                            class="
                                badge badge-sm
                                {{ $statusMeta['badge'] }}
                            "
                        >
                            {{ $order->status->value }}
                        </span>
                    </div>

                    <p
                        class="
                            mt-1.5
                            max-w-2xl
                            text-sm leading-7
                            text-base-content/50
                        "
                    >
                        {{ $statusMeta['description'] }}
                    </p>
                </div>
            </div>

            @if($shouldPoll)
                <div
                    class="
                        flex shrink-0
                        items-center gap-2
                        text-xs text-base-content/40
                    "
                >
                    <span class="loading loading-spinner loading-xs"></span>
                    به‌روزرسانی خودکار
                </div>
            @endif
        </div>
    </section>

    @if($paymentNotice)
        <div
            @class([
                '
                    rounded-xl border
                    px-4 py-3
                    text-sm leading-7
                ',
                'border-warning/20 bg-warning/5 text-base-content/70' =>
                    $paymentNotice['type'] === 'warning',
                'border-error/20 bg-error/5 text-error' =>
                    $paymentNotice['type'] === 'error',
                'border-info/20 bg-info/5 text-base-content/70' =>
                    $paymentNotice['type'] === 'info',
            ])
        >
            {{ $paymentNotice['message'] }}
        </div>
    @endif

    {{-- Progress --}}
    <section
        class="
            rounded-2xl
            border border-base-300
            bg-base-100
            p-5
        "
    >
        <h2 class="font-semibold text-base-content">
            مسیر آماده‌سازی
        </h2>

        <div
            class="
                mt-5
                grid grid-cols-1 gap-3
                md:grid-cols-4
            "
        >
            @php
                $status = $order->status;
                $serverReady = $server?->isActive() ?? false;

                $paymentDone = in_array(
                    $status,
                    [
                        \App\Domain\Billing\Enums\OrderStatus::Paid,
                        \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                        \App\Domain\Billing\Enums\OrderStatus::Fulfilled,
                    ],
                    true,
                );

                $queued = in_array(
                    $status,
                    [
                        \App\Domain\Billing\Enums\OrderStatus::Paid,
                        \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                        \App\Domain\Billing\Enums\OrderStatus::Fulfilled,
                    ],
                    true,
                );

                $providerDone =
                    $status ===
                    \App\Domain\Billing\Enums\OrderStatus::Fulfilled;
            @endphp

            <div
                @class([
                    'rounded-xl border p-4',
                    'border-success/25 bg-success/5' => $paymentDone,
                    'border-primary/25 bg-primary/5' =>
                        ! $paymentDone &&
                        $status ===
                        \App\Domain\Billing\Enums\OrderStatus::PendingPayment,
                    'border-base-300' =>
                        ! $paymentDone &&
                        $status !==
                        \App\Domain\Billing\Enums\OrderStatus::PendingPayment,
                ])
            >
                <div class="flex items-center gap-2">
                    <x-icon
                        :name="$paymentDone ? 'lucide.circle-check' : 'lucide.credit-card'"
                        @class([
                            '!size-4.5',
                            'text-success' => $paymentDone,
                            'text-primary' => ! $paymentDone,
                        ])
                    />

                    <span class="text-sm font-medium">
                        پرداخت
                    </span>
                </div>

                <p class="mt-2 text-xs leading-5 text-base-content/40">
                    تأیید تراکنش مالی
                </p>
            </div>

            <div
                @class([
                    'rounded-xl border p-4',
                    'border-success/25 bg-success/5' =>
                        $status ===
                        \App\Domain\Billing\Enums\OrderStatus::Provisioning
                        || $status ===
                        \App\Domain\Billing\Enums\OrderStatus::Fulfilled,
                    'border-primary/25 bg-primary/5' =>
                        $status ===
                        \App\Domain\Billing\Enums\OrderStatus::Paid,
                    'border-base-300' =>
                        ! in_array(
                            $status,
                            [
                                \App\Domain\Billing\Enums\OrderStatus::Paid,
                                \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                                \App\Domain\Billing\Enums\OrderStatus::Fulfilled,
                            ],
                            true,
                        ),
                ])
            >
                <div class="flex items-center gap-2">
                    <x-icon
                        name="lucide.list-checks"
                        class="!size-4.5 text-base-content/55"
                    />

                    <span class="text-sm font-medium">
                        ثبت ساخت
                    </span>
                </div>

                <p class="mt-2 text-xs leading-5 text-base-content/40">
                    ورود سفارش به صف Provisioning
                </p>
            </div>

            <div
                @class([
                    'rounded-xl border p-4',
                    'border-success/25 bg-success/5' => $providerDone,
                    'border-primary/25 bg-primary/5' =>
                        $status ===
                        \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                    'border-base-300' =>
                        ! $providerDone &&
                        $status !==
                        \App\Domain\Billing\Enums\OrderStatus::Provisioning,
                ])
            >
                <div class="flex items-center gap-2">
                    <x-icon
                        name="lucide.cloud-cog"
                        class="!size-4.5 text-base-content/55"
                    />

                    <span class="text-sm font-medium">
                        ساخت VPS
                    </span>
                </div>

                <p class="mt-2 text-xs leading-5 text-base-content/40">
                    ایجاد منبع در Cloud Provider
                </p>
            </div>

            <div
                @class([
                    'rounded-xl border p-4',
                    'border-success/25 bg-success/5' => $serverReady,
                    'border-primary/25 bg-primary/5' =>
                        $providerDone && ! $serverReady,
                    'border-base-300' => ! $providerDone,
                ])
            >
                <div class="flex items-center gap-2">
                    <x-icon
                        :name="$serverReady ? 'lucide.circle-check' : 'lucide.server'"
                        @class([
                            '!size-4.5',
                            'text-success' => $serverReady,
                            'text-base-content/55' => ! $serverReady,
                        ])
                    />

                    <span class="text-sm font-medium">
                        اتصال xDeploy
                    </span>
                </div>

                <p class="mt-2 text-xs leading-5 text-base-content/40">
                    بررسی آمادگی SSH
                </p>
            </div>
        </div>
    </section>

    <div
        class="
            grid grid-cols-1 gap-5
            lg:grid-cols-[minmax(0,1fr)_320px]
        "
    >
        {{-- Order details --}}
        <section
            class="
                rounded-2xl
                border border-base-300
                bg-base-100
                p-5
            "
        >
            <h2 class="font-semibold text-base-content">
                مشخصات سفارش
            </h2>

            <dl
                class="
                    mt-5
                    grid grid-cols-1 gap-x-8 gap-y-4
                    sm:grid-cols-2
                "
            >
                <div>
                    <dt class="text-xs text-base-content/40">
                        Region
                    </dt>
                    <dd
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            font-medium text-base-content
                        "
                    >
                        {{ $order->region_id }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/40">
                        Plan
                    </dt>
                    <dd
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            font-medium text-base-content
                        "
                    >
                        {{ $order->size_id }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/40">
                        سیستم‌عامل
                    </dt>
                    <dd
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            font-medium text-base-content
                        "
                    >
                        {{ $order->image_distribution }}
                        {{ $order->image_version }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/40">
                        دیسک
                    </dt>
                    <dd
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            font-medium text-base-content
                        "
                    >
                        {{ $order->selected_disk_gib }} GB
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/40">
                        دوره
                    </dt>
                    <dd class="mt-1 text-sm font-medium text-base-content">
                        {{ $periodLabel }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/40">
                        زمان ثبت
                    </dt>
                    <dd
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            text-base-content/70
                        "
                    >
                        {{ $order->created_at?->format('Y-m-d H:i') }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Commercial / action card --}}
        <aside
            class="
                rounded-2xl
                border border-base-300
                bg-base-100
                p-5
            "
        >
            <div class="text-xs text-base-content/40">
                مبلغ سفارش
            </div>

            <div
                dir="ltr"
                class="
                    technical-value
                    mt-1.5 text-left
                    text-2xl font-semibold
                    tracking-tight
                    text-base-content
                "
            >
                {{ number_format($order->final_amount) }}

                <span
                    class="
                        text-sm font-normal
                        text-base-content/40
                    "
                >
                    ریال
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
                        btn-primary
                        mt-5 w-full
                        rounded-xl
                        font-medium
                    "
                />
            @elseif(
                $order->status ===
                \App\Domain\Billing\Enums\OrderStatus::Expired
                || $order->status ===
                \App\Domain\Billing\Enums\OrderStatus::Cancelled
            )
                <x-button
                    label="ایجاد سفارش جدید"
                    icon="lucide.plus"
                    :link="route('panel.servers.buy')"
                    wire:navigate
                    class="
                        btn-primary
                        mt-5 w-full
                        rounded-xl
                        font-medium
                    "
                />
            @endif

            @if($server)
                <div class="my-5 border-t border-base-300"></div>

                <div class="text-xs text-base-content/40">
                    سرور ایجادشده
                </div>

                <div class="mt-2 font-medium text-base-content">
                    {{ $server->name }}
                </div>

                @if($server->hasConnectionHost())
                    <div
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 text-left text-sm
                            text-base-content/55
                        "
                    >
                        {{ $server->host }}:{{ $server->port }}
                    </div>
                @endif

                @if($server->isActive())
                    <x-button
                        label="مدیریت سرور"
                        icon="lucide.server-cog"
                        :link="route('panel.servers.dashboard', $server)"
                        wire:navigate
                        class="
                            btn-primary
                            mt-4 w-full
                            rounded-xl
                            font-medium
                        "
                    />
                @else
                    <p
                        class="
                            mt-3
                            text-xs leading-6
                            text-base-content/45
                        "
                    >
                        VPS در Cloud ساخته شده، اما اتصال xDeploy هنوز Active نشده است.
                    </p>
                @endif
            @endif

            @if(
                $order->status ===
                \App\Domain\Billing\Enums\OrderStatus::Failed
            )
                <div
                    class="
                        mt-5
                        rounded-xl
                        border border-error/15
                        bg-error/5
                        p-3
                        text-xs leading-6
                        text-base-content/60
                    "
                >
                    برای جلوگیری از ساخت VPS تکراری و هزینه مجدد،
                    از این صفحه عملیات Provisioning دوباره اجرا نمی‌شود.
                </div>
            @endif
        </aside>
    </div>
</div>
