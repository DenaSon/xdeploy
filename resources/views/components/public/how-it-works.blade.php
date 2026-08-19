@php
    $steps = [
        [
            'number' => '01',
            'icon' => 'lucide.server',
            'title' => 'اتصال یا تهیه VPS',
            'description' => 'VPS فعلی خود را متصل کنید یا از داخل Coreflare یک سرور جدید متناسب با نیازتان تهیه کنید.',
        ],
        [
            'number' => '02',
            'icon' => 'lucide.shield-check',
            'title' => 'بررسی و آماده‌سازی',
            'description' => 'اتصال SSH، سیستم‌عامل، سطح دسترسی و پیش‌نیازهای ضروری بررسی می‌شوند تا سرور آماده ادامه کار باشد.',
        ],
        [
            'number' => '03',
            'icon' => 'lucide.package-plus',
            'title' => 'راه‌اندازی سرویس',
            'description' => 'سرویس موردنظر را انتخاب کنید تا نصب، پیش‌نیازها و تنظیمات اولیه به‌صورت کنترل‌شده انجام شوند.',
        ],
        [
            'number' => '04',
            'icon' => 'lucide.layout-dashboard',
            'title' => 'مدیریت از یک پنل',
            'description' => 'منابع، سرویس‌ها، دامنه‌ها و وضعیت اجرا را از همان پنل مشاهده و مدیریت کنید.',
            'highlight' => true,
        ],
    ];
@endphp

<section
    id="how-it-works"
    class="
        relative scroll-mt-24 overflow-hidden
        border-b border-base-300/60 bg-base-200/35
    "
>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 overflow-hidden"
    >
        <div
            class="
                absolute -start-40 top-20 size-[28rem]
                rounded-full bg-primary/[0.035] blur-3xl
            "
        ></div>
        <div
            class="
                absolute -end-48 bottom-0 size-[32rem]
                rounded-full bg-accent/[0.025] blur-3xl
            "
        ></div>
    </div>

    <div
        class="
            relative mx-auto w-full max-w-7xl
            px-4 py-20
            sm:px-6 sm:py-24
            lg:px-8 lg:py-28
        "
    >
        <div class="mx-auto max-w-2xl text-center">
            <span
                class="
                    inline-flex items-center gap-2 rounded-full
                    border border-primary/15 bg-primary/[0.055]
                    px-3 py-1.5 text-xs font-medium text-primary
                "
            >
                <x-icon name="lucide.workflow" class="!size-3.5 stroke-[1.8]" />
                مسیر راه‌اندازی
            </span>

            <h2
                class="
                    mt-5 text-3xl font-semibold leading-[1.45]
                    tracking-tight text-base-content sm:text-4xl
                "
            >
                از اتصال سرور تا سرویس آماده،
                <span class="text-primary">مسیر مشخص است</span>
            </h2>

            <p
                class="
                    mx-auto mt-4 max-w-xl text-sm leading-7
                    text-base-content/55 sm:text-base sm:leading-8
                "
            >
                سرور را متصل یا تهیه کنید؛ Coreflare آماده‌سازی، نصب
                و مدیریت سرویس را در یک جریان مشخص کنار هم قرار می‌دهد.
            </p>
        </div>

        <div
            x-data="{ visible: false }"
            x-intersect.once="visible = true"
            class="
                relative mt-14 grid gap-4
                md:grid-cols-2
                lg:mt-16 lg:grid-cols-4 lg:gap-5
            "
        >
            <div
                aria-hidden="true"
                class="
                    pointer-events-none absolute start-[12.5%] end-[12.5%] top-7
                    hidden h-px bg-gradient-to-l
                    from-base-300/30 via-base-300 to-base-300/30 lg:block
                "
            ></div>

            @foreach($steps as $index => $step)
                @php
                    $highlight = (bool) ($step['highlight'] ?? false);
                    $delayClass = match ($index) {
                        1 => 'delay-75',
                        2 => 'delay-150',
                        3 => 'delay-200',
                        default => '',
                    };
                @endphp

                <article
                    :class="visible
                        ? 'opacity-100 translate-y-0'
                        : 'opacity-0 translate-y-3'"
                    @class([
                        'group relative rounded-2xl p-5 shadow-sm backdrop-blur-sm transition-all duration-500 ease-out hover:-translate-y-0.5 hover:shadow-lg',
                        $delayClass,
                        'overflow-hidden border border-primary/15 bg-primary/[0.045] shadow-primary/[0.03] hover:border-primary/25 hover:shadow-primary/[0.06]' => $highlight,
                        'border border-base-300/80 bg-base-100/85 shadow-base-content/[0.015] hover:border-primary/20 hover:shadow-base-content/[0.035]' => ! $highlight,
                    ])
                >
                    @if($highlight)
                        <div
                            aria-hidden="true"
                            class="
                                absolute -end-14 -top-14 size-32
                                rounded-full bg-primary/[0.09] blur-2xl
                            "
                        ></div>
                    @endif

                    <div class="relative z-10 flex items-center justify-between">
                        <span
                            @class([
                                'flex size-14 items-center justify-center rounded-2xl',
                                'bg-primary text-primary-content shadow-lg shadow-primary/15' => $highlight,
                                'border border-base-300/80 bg-base-100 text-primary shadow-sm shadow-base-content/[0.025] transition-colors duration-200 group-hover:border-primary/15 group-hover:bg-primary/[0.05]' => ! $highlight,
                            ])
                        >
                            <x-icon :name="$step['icon']" class="!size-6 stroke-[1.7]" />
                        </span>

                        <span
                            dir="ltr"
                            @class([
                                'numeric-value text-[11px] font-semibold',
                                'text-primary/45' => $highlight,
                                'text-base-content/25' => ! $highlight,
                            ])
                        >
                            {{ $step['number'] }}
                        </span>
                    </div>

                    <h3 class="relative mt-6 text-base font-semibold text-base-content">
                        {{ $step['title'] }}
                    </h3>

                    <p class="relative mt-2 text-sm leading-7 text-base-content/50">
                        {{ $step['description'] }}
                    </p>
                </article>
            @endforeach
        </div>

        <div
            class="
                mx-auto mt-9 flex w-fit max-w-full flex-wrap
                items-center justify-center gap-x-3 gap-y-2
                rounded-full border border-base-300/70 bg-base-100/65
                px-4 py-2.5 text-xs text-base-content/40 backdrop-blur-sm
            "
        >
            <span dir="ltr">VPS</span>
            <x-icon name="lucide.chevron-left" class="!size-3.5 stroke-[1.5]" />
            <span>آماده‌سازی</span>
            <x-icon name="lucide.chevron-left" class="!size-3.5 stroke-[1.5]" />
            <span>راه‌اندازی سرویس</span>
            <x-icon name="lucide.chevron-left" class="!size-3.5 stroke-[1.5]" />
            <span class="inline-flex items-center gap-1.5 font-medium text-success">
                <span class="size-1.5 rounded-full bg-success"></span>
                آماده مدیریت
            </span>
        </div>
    </div>
</section>
