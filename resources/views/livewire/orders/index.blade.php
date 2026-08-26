<div dir="rtl" class="space-y-4">
    <header
        class="
            flex flex-col gap-4
            sm:flex-row
            sm:items-center
            sm:justify-between
        "
    >
        <div class="flex min-w-0 items-start gap-3">
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
                <h1
                    class="
                        text-xl font-semibold
                        tracking-tight
                        text-base-content
                        sm:text-2xl
                    "
                >
                    سفارش‌ها
                </h1>

                <p
                    class="
                        mt-1
                        text-xs leading-6
                        text-base-content/45
                        sm:text-sm
                    "
                >
                    وضعیت پرداخت و آماده‌سازی سفارش‌های خود را از اینجا دنبال کنید.
                </p>
            </div>
        </div>

        <x-button
            label="خرید VPS"
            icon="lucide.plus"
            :link="route('panel.servers.buy')"
            wire:navigate
            class="
                btn-primary btn-sm
                self-start rounded-xl
                px-4 font-medium
                sm:self-auto
            "
        />
    </header>

    @if($orders->isEmpty())
        <section
            class="
                flex min-h-64 flex-col
                items-center justify-center
                rounded-2xl
                border border-dashed border-base-300
                bg-base-100
                px-6 py-10
                text-center
            "
        >
            <div
                class="
                    flex size-12
                    items-center justify-center
                    rounded-2xl
                    bg-base-200
                    text-base-content/40
                "
            >
                <x-icon
                    name="lucide.receipt"
                    class="!size-5 stroke-[1.7]"
                />
            </div>

            <h2
                class="
                    mt-4
                    text-sm font-semibold
                    text-base-content
                "
            >
                هنوز سفارشی ندارید
            </h2>

            <p
                class="
                    mt-1
                    max-w-md
                    text-xs leading-6
                    text-base-content/45
                "
            >
                پس از ثبت سفارش خرید یا تمدید VPS، وضعیت آن در این بخش نمایش داده می‌شود.
            </p>

            <x-button
                label="خرید اولین VPS"
                icon="lucide.server-plus"
                :link="route('panel.servers.buy')"
                wire:navigate
                class="
                    btn-primary btn-sm
                    mt-5 rounded-xl
                    px-4 font-medium
                "
            />
        </section>
    @else
        <section class="space-y-3">
            @foreach($orders as $order)
                @php
                    $meta = $presentation[(int) $order->id];
                    $state = $meta['state'];
                    $payment = $meta['payment'];
                @endphp

                <article
                    wire:key="customer-order-{{ $order->id }}"
                    class="
                        overflow-hidden
                        rounded-2xl
                        border border-base-300
                        bg-base-100
                        transition-colors duration-200
                        hover:border-primary/20
                    "
                >
                    <div class="p-4 sm:p-5">
                        <div
                            class="
                                flex flex-col gap-3
                                sm:flex-row
                                sm:items-start
                                sm:justify-between
                            "
                        >
                            <div class="min-w-0">
                                <div
                                    class="
                                        flex flex-wrap
                                        items-center gap-2
                                    "
                                >
                                    <span
                                        class="
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        {{ $meta['type'] }}
                                    </span>

                                    <span
                                        dir="ltr"
                                        class="
                                            technical-value
                                            text-[11px]
                                            text-base-content/35
                                        "
                                    >
                                        #{{ $order->id }}
                                    </span>

                                    <span
                                        @class([
                                            '
                                                inline-flex items-center gap-1.5
                                                rounded-full
                                                px-2.5 py-1
                                                text-[10px] font-medium
                                            ',
                                            'bg-success/10 text-success' =>
                                                $state['tone'] === 'success',
                                            'bg-primary/10 text-primary' =>
                                                $state['tone'] === 'primary',
                                            'bg-info/10 text-info' =>
                                                $state['tone'] === 'info',
                                            'bg-warning/10 text-warning' =>
                                                $state['tone'] === 'warning',
                                            'bg-error/10 text-error' =>
                                                $state['tone'] === 'error',
                                            'bg-base-200 text-base-content/55' =>
                                                $state['tone'] === 'neutral',
                                        ])
                                    >
                                        <x-icon
                                            :name="$state['icon']"
                                            @class([
                                                '!size-3.5 stroke-[1.9]',
                                                'animate-spin' =>
                                                    $state['icon'] === 'lucide.loader-circle',
                                            ])
                                        />

                                        {{ $state['label'] }}
                                    </span>
                                </div>

                                <p
                                    class="
                                        mt-2
                                        max-w-2xl
                                        text-xs leading-6
                                        text-base-content/50
                                    "
                                >
                                    {{ $state['description'] }}
                                </p>
                            </div>

                            <div
                                dir="ltr"
                                class="
                                    technical-value
                                    shrink-0 text-left
                                    text-[11px]
                                    text-base-content/35
                                "
                            >
                                {{ $order->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>

                        <div
                            class="
                                mt-4
                                grid grid-cols-2 gap-3
                                rounded-xl
                                bg-base-200/45
                                p-3
                                sm:grid-cols-4
                            "
                        >
                            <div class="min-w-0">
                                <div
                                    class="text-[10px] text-base-content/35"
                                >
                                    ارائه‌دهنده
                                </div>
                                <div
                                    class="
                                        mt-1 truncate
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    {{ $meta['provider'] }}
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div
                                    class="text-[10px] text-base-content/35"
                                >
                                    موقعیت
                                </div>
                                <div
                                    class="
                                        mt-1 truncate
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    {{ $meta['region'] }}
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div
                                    class="text-[10px] text-base-content/35"
                                >
                                    سیستم‌عامل
                                </div>
                                <div
                                    dir="ltr"
                                    class="
                                        technical-value
                                        mt-1 truncate text-left
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    {{ $order->image_distribution }}
                                    {{ $order->image_version }}
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div
                                    class="text-[10px] text-base-content/35"
                                >
                                    دوره
                                </div>
                                <div
                                    class="
                                        mt-1 truncate
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    {{ $meta['period'] }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="
                            flex flex-col gap-3
                            border-t border-base-300
                            bg-base-100
                            px-4 py-3
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex flex-wrap items-center gap-x-5 gap-y-2
                            "
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[10px] text-base-content/35"
                                >
                                    پرداخت
                                </span>

                                <span
                                    @class([
                                        'text-xs font-medium',
                                        'text-success' =>
                                            $payment['tone'] === 'success',
                                        'text-info' =>
                                            $payment['tone'] === 'info',
                                        'text-error' =>
                                            $payment['tone'] === 'error',
                                        'text-base-content/50' =>
                                            $payment['tone'] === 'neutral',
                                    ])
                                >
                                    {{ $payment['label'] }}
                                </span>
                            </div>

                            <div class="flex items-baseline gap-1.5">
                                <span
                                    class="text-[10px] text-base-content/35"
                                >
                                    مبلغ
                                </span>

                                <span
                                    dir="ltr"
                                    class="
                                        technical-value
                                        text-sm font-semibold
                                        text-base-content
                                    "
                                >
                                    {{ $meta['amount'] }}
                                </span>

                                <span
                                    class="text-[10px] text-base-content/40"
                                >
                                    تومان
                                </span>
                            </div>
                        </div>

                        <x-button
                            label="مشاهده جزئیات"
                            icon="lucide.arrow-left"
                            :link="route(
                                'panel.orders.show',
                                $order,
                            )"
                            wire:navigate
                            class="
                                btn-ghost btn-sm
                                self-start rounded-xl
                                text-base-content/60
                                sm:self-auto
                            "
                        />
                    </div>
                </article>
            @endforeach
        </section>

        @if($orders->hasPages())
            <div class="pt-2">
                {{ $orders->links() }}
            </div>
        @endif
    @endif
</div>
