<section
    id="capabilities"
    class="
        relative
        overflow-hidden
        border-b border-base-300/60
        bg-base-100
    "
>
    {{-- Background atmosphere --}}
    <div
        aria-hidden="true"
        class="
            pointer-events-none
            absolute inset-0
            overflow-hidden
        "
    >
        <div
            class="
                absolute
                -start-52 top-40
                size-[34rem]
                rounded-full
                bg-primary/[0.035]
                blur-3xl
            "
        ></div>

        <div
            class="
                absolute
                -end-52 bottom-32
                size-[36rem]
                rounded-full
                bg-accent/[0.025]
                blur-3xl
            "
        ></div>
    </div>


    <div
        class="
            relative

            mx-auto
            w-full max-w-7xl

            px-4 py-20

            sm:px-6 sm:py-24

            lg:px-8 lg:py-28
        "
    >
        {{-- Heading --}}
        <div
            class="
                mx-auto
                max-w-2xl
                text-center
            "
        >
            <span
                class="
                    inline-flex
                    items-center gap-2

                    rounded-full
                    border border-primary/15
                    bg-primary/[0.055]

                    px-3 py-1.5

                    text-xs font-medium
                    text-primary
                "
            >
                <x-icon
                    name="lucide.layout-grid"
                    class="!size-3.5 stroke-[1.8]"
                />

                قابلیت‌های Coreflare
            </span>


            <h2
                class="
                    mt-5

                    text-3xl
                    font-semibold
                    leading-[1.45]
                    tracking-tight
                    text-base-content

                    sm:text-4xl
                "
            >
                ابزارهای اصلی مدیریت زیرساخت،

                <span class="text-primary">
                    در یک محیط
                </span>
            </h2>


            <p
                class="
                    mx-auto mt-4
                    max-w-xl

                    text-sm
                    leading-7
                    text-base-content/55

                    sm:text-base
                    sm:leading-8
                "
            >
                از وضعیت سرور و منابع تا سرویس‌ها، دامنه، کنسول و چرخه عمر VPS؛
                هر بخش زمانی در دسترس است که برای مدیریت شما لازم باشد.
            </p>
        </div>


        {{-- Bento --}}
        <div
            class="
                mt-14

                grid gap-4

                lg:mt-16
                lg:grid-cols-12
            "
        >
            {{-- Server management --}}
            <article
                class="
                    group
                    relative
                    overflow-hidden

                    rounded-2xl

                    border border-base-300/80
                    bg-base-200/35

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.025]

                    sm:p-6

                    lg:col-span-7
                "
            >
                <div
                    aria-hidden="true"
                    class="
                        pointer-events-none

                        absolute
                        -end-20 -top-20

                        size-56
                        rounded-full

                        bg-primary/[0.065]
                        blur-3xl
                    "
                ></div>


                <div class="relative">
                    <div
                        class="
                            flex size-10
                            items-center justify-center

                            rounded-xl
                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-5 stroke-[1.8]"
                        />
                    </div>


                    <h3
                        class="
                            mt-5

                            text-lg font-semibold
                            text-base-content
                        "
                    >
                        وضعیت سرور، واضح و قابل اتکا
                    </h3>


                    <p
                        class="
                            mt-2
                            max-w-md

                            text-sm
                            leading-7
                            text-base-content/50
                        "
                    >
                        اتصال، سیستم‌عامل، منابع و آمادگی سرور را در یک نمای مشخص
                        ببینید؛ بدون بررسی دستی برای اطلاعات پایه.
                    </p>


                    {{-- Server preview --}}
                    <div
                        class="
                            mt-7

                            rounded-2xl

                            border border-base-300/80
                            bg-base-100/90

                            p-4

                            shadow-sm
                            shadow-base-content/[0.02]
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
                                        bg-base-200
                                        text-base-content/65
                                    "
                                >
                                    <x-icon
                                        name="lucide.server"
                                        class="!size-5 stroke-[1.7]"
                                    />
                                </span>


                                <div class="min-w-0">
                                    <div
                                        class="
                                            truncate
                                            text-sm font-medium
                                            text-base-content
                                        "
                                    >
                                        Production VPS
                                    </div>

                                    <div
                                        dir="ltr"
                                        class="
                                            mt-1

                                            text-[11px]
                                            text-base-content/40
                                        "
                                    >
                                        Ubuntu 24.04 · 185.10.20.41
                                    </div>
                                </div>
                            </div>


                            <span
                                class="
                                    inline-flex
                                    w-fit
                                    items-center gap-2

                                    rounded-full
                                    bg-success/10

                                    px-3 py-1.5

                                    text-xs font-medium
                                    text-success
                                "
                            >
                                <span
                                    class="
                                        size-1.5
                                        rounded-full
                                        bg-success
                                    "
                                ></span>

                                آماده مدیریت
                            </span>
                        </div>


                        <div
                            class="
                                mt-4

                                grid grid-cols-2 gap-2

                                sm:grid-cols-4
                            "
                        >
                            <div
                                class="
                                    rounded-xl
                                    bg-base-200/60
                                    p-3
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    سیستم‌عامل
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                        mt-1
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    Ubuntu 24.04
                                </div>
                            </div>


                            <div
                                class="
                                    rounded-xl
                                    bg-base-200/60
                                    p-3
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    اتصال
                                </div>

                                <div
                                    class="
                                        mt-1
                                        text-xs font-medium
                                        text-success
                                    "
                                >
                                    برقرار
                                </div>
                            </div>


                            <div
                                class="
                                    rounded-xl
                                    bg-base-200/60
                                    p-3
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    CPU
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                        mt-1
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    2 Core
                                </div>
                            </div>


                            <div
                                class="
                                    rounded-xl
                                    bg-base-200/60
                                    p-3
                                "
                            >
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    RAM
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                        mt-1
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    4 GB
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>


            {{-- Monitoring --}}
            <article
                class="
                    rounded-2xl

                    border border-base-300/80
                    bg-base-100

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.025]

                    sm:p-6

                    lg:col-span-5
                "
            >
                <div
                    class="
                        flex size-10
                        items-center justify-center

                        rounded-xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.activity"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>


                <h3
                    class="
                        mt-5

                        text-lg font-semibold
                        text-base-content
                    "
                >
                    منابع سرور، در یک نگاه
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    مصرف پردازنده، حافظه، فضای ذخیره‌سازی و Load را
                    در یک نمای ساده و خوانا دنبال کنید.
                </p>


                <div
                    class="
                        mt-7

                        grid grid-cols-2
                        gap-3
                    "
                >
                    @foreach([
                        ['CPU', '24%', 24],
                        ['RAM', '48%', 48],
                        ['Disk', '31%', 31],
                        ['Load', '0.42', 18],
                    ] as [$label, $value, $progress])
                        <div
                            class="
                                rounded-xl
                                bg-base-200/60
                                p-3.5
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-center justify-between
                                    gap-2
                                "
                            >
                                <span
                                    dir="ltr"
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    {{ $label }}
                                </span>

                                <span
                                    dir="ltr"
                                    class="
                                        text-xs font-medium
                                        text-base-content/75
                                    "
                                >
                                    {{ $value }}
                                </span>
                            </div>

                            <progress
                                class="
                                    progress progress-primary
                                    mt-3 h-1
                                    w-full
                                "
                                value="{{ $progress }}"
                                max="100"
                            ></progress>
                        </div>
                    @endforeach
                </div>
            </article>


            {{-- Applications --}}
            <article
                class="
                    rounded-2xl

                    border border-base-300/80
                    bg-base-100

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.025]

                    sm:p-6

                    lg:col-span-5
                "
            >
                <div
                    class="
                        flex size-10
                        items-center justify-center

                        rounded-xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.package-check"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>


                <h3
                    class="
                        mt-5

                        text-lg font-semibold
                        text-base-content
                    "
                >
                    سرویس‌های آماده راه‌اندازی
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    سرویس‌های پشتیبانی‌شده را نصب کنید و وضعیت اجرا و قابلیت‌های
                    اختصاصی هرکدام را از Coreflare مدیریت کنید.
                </p>


                <div class="mt-6 space-y-2">
                    @foreach([
                        ['Marzban', 'مدیریت سرویس Xray', 'lucide.shield-check'],
                        ['n8n', 'اتوماسیون و گردش‌کار', 'lucide.workflow'],
                        ['AmneziaWG', 'VPN و مدیریت دستگاه‌ها', 'lucide.network'],
                        ['WordPress', 'وب‌سایت و مدیریت محتوا', 'lucide.globe'],
                    ] as [$name, $description, $icon])
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3

                                rounded-xl
                                bg-base-200/55

                                px-3.5 py-3
                            "
                        >
                            <div
                                class="
                                    flex min-w-0
                                    items-center gap-2.5
                                "
                            >
                                <span
                                    class="
                                        flex size-8 shrink-0
                                        items-center justify-center

                                        rounded-lg
                                        bg-base-100
                                        text-primary
                                    "
                                >
                                    <x-icon
                                        :name="$icon"
                                        class="!size-4 stroke-[1.7]"
                                    />
                                </span>

                                <div class="min-w-0">
                                    <div
                                        dir="ltr"
                                        class="
                                            text-xs font-medium
                                            text-base-content/75
                                        "
                                    >
                                        {{ $name }}
                                    </div>

                                    <div
                                        class="
                                            mt-0.5

                                            truncate
                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        {{ $description }}
                                    </div>
                                </div>
                            </div>

                            <span
                                class="
                                    size-1.5 shrink-0
                                    rounded-full
                                    bg-success
                                "
                            ></span>
                        </div>
                    @endforeach
                </div>
            </article>


            {{-- Domain + HTTPS --}}
            <article
                class="
                    group
                    relative
                    overflow-hidden

                    rounded-2xl

                    border border-primary/15
                    bg-primary/[0.04]

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/25
                    hover:shadow-lg
                    hover:shadow-primary/[0.04]

                    sm:p-6

                    lg:col-span-7
                "
            >
                <div
                    aria-hidden="true"
                    class="
                        pointer-events-none

                        absolute
                        -bottom-24 -end-20

                        size-64
                        rounded-full

                        bg-primary/[0.07]
                        blur-3xl
                    "
                ></div>


                <div class="relative">
                    <div
                        class="
                            flex size-10
                            items-center justify-center

                            rounded-xl
                            bg-primary
                            text-primary-content
                        "
                    >
                        <x-icon
                            name="lucide.globe"
                            class="!size-5 stroke-[1.8]"
                        />
                    </div>


                    <h3
                        class="
                            mt-5

                            text-lg font-semibold
                            text-base-content
                        "
                    >
                        دامنه و HTTPS، از همان مسیر
                    </h3>


                    <p
                        class="
                            mt-2
                            max-w-xl

                            text-sm
                            leading-7
                            text-base-content/50
                        "
                    >
                        برای سرویس‌های پشتیبانی‌شده، دامنه را متصل کنید؛ Coreflare
                        پیش‌نیازها را بررسی می‌کند و HTTPS را بدون درگیری با
                        پیکربندی زیرساختی فعال می‌کند.
                    </p>


                    {{-- Endpoint preview --}}
                    <div
                        class="
                            mt-7

                            rounded-2xl

                            border border-primary/10
                            bg-base-100/90

                            p-4
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
                                        bg-success/10
                                        text-success
                                    "
                                >
                                    <x-icon
                                        name="lucide.lock-keyhole"
                                        class="!size-[18px] stroke-[1.8]"
                                    />
                                </span>

                                <div class="min-w-0">
                                    <div
                                        dir="ltr"
                                        class="
                                            truncate
                                            text-sm font-medium
                                            text-base-content
                                        "
                                    >
                                        app.example.com
                                    </div>

                                    <div
                                        class="
                                            mt-1
                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        دسترسی عمومی امن
                                    </div>
                                </div>
                            </div>


                            <span
                                class="
                                    inline-flex
                                    w-fit
                                    items-center gap-1.5

                                    rounded-full
                                    bg-success/10

                                    px-3 py-1.5

                                    text-xs font-medium
                                    text-success
                                "
                            >
                                <x-icon
                                    name="lucide.circle-check"
                                    class="!size-3.5 stroke-[1.8]"
                                />

                                HTTPS فعال
                            </span>
                        </div>


                        <div
                            class="
                                mt-4

                                grid gap-2

                                sm:grid-cols-3
                            "
                        >
                            @foreach([
                                'بررسی دامنه',
                                'آماده‌سازی سرویس',
                                'فعال‌سازی HTTPS',
                            ] as $label)
                                <div
                                    class="
                                        flex
                                        items-center gap-2

                                        rounded-xl
                                        bg-base-200/60

                                        px-3 py-2.5
                                    "
                                >
                                    <x-icon
                                        name="lucide.check"
                                        class="
                                            !size-3.5
                                            shrink-0
                                            text-success
                                            stroke-[2]
                                        "
                                    />

                                    <span
                                        class="
                                            text-[11px]
                                            text-base-content/60
                                        "
                                    >
                                        {{ $label }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>


            {{-- Console --}}
            <article
                class="
                    rounded-2xl

                    border border-base-300/80
                    bg-base-200/35

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.025]

                    sm:p-6

                    lg:col-span-5
                "
            >
                <div
                    class="
                        flex size-10
                        items-center justify-center

                        rounded-xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.terminal"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>


                <h3
                    class="
                        mt-5

                        text-lg font-semibold
                        text-base-content
                    "
                >
                    دسترسی مستقیم هنگام نیاز
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    برای VPSهای ابری پشتیبانی‌شده، اگر SSH در دسترس نباشد
                    می‌توانید از کنسول مستقیم برای بررسی و بازیابی سرور استفاده کنید.
                </p>


                {{-- Console preview --}}
                <div
                    dir="ltr"
                    class="
                        mt-7

                        overflow-hidden
                        rounded-2xl

                        border border-base-300
                        bg-base-content

                        text-base-100
                    "
                >
                    <div
                        class="
                            flex
                            items-center justify-between

                            border-b
                            border-base-100/10

                            px-3.5 py-2.5
                        "
                    >
                        <div class="flex gap-1.5">
                            <span
                                class="
                                    size-1.5
                                    rounded-full
                                    bg-error
                                "
                            ></span>

                            <span
                                class="
                                    size-1.5
                                    rounded-full
                                    bg-warning
                                "
                            ></span>

                            <span
                                class="
                                    size-1.5
                                    rounded-full
                                    bg-success
                                "
                            ></span>
                        </div>

                        <span
                            class="
                                text-[9px]
                                text-base-100/35
                            "
                        >
                            SERVER CONSOLE
                        </span>
                    </div>


                    <div
                        class="
                            space-y-2

                            px-4 py-5

                            font-mono
                            text-[10px]
                            text-base-100/55
                        "
                    >
                        <div>
                            Ubuntu 24.04 LTS
                        </div>

                        <div>
                            server login:
                            <span class="text-base-100/80">
                                _
                            </span>
                        </div>
                    </div>
                </div>


                <div
                    class="
                        mt-3

                        text-[10px]
                        leading-5
                        text-base-content/35
                    "
                >
                    برای VPSهای ابری پشتیبانی‌شده در دسترس است.
                </div>
            </article>


            {{-- Lifecycle --}}
            <article
                class="
                    relative
                    overflow-hidden

                    rounded-2xl

                    border border-primary/15
                    bg-primary/[0.04]

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/25
                    hover:shadow-lg
                    hover:shadow-primary/[0.04]

                    sm:p-6

                    lg:col-span-7
                "
            >
                <div
                    aria-hidden="true"
                    class="
                        pointer-events-none

                        absolute
                        -start-20 -top-24

                        size-56
                        rounded-full

                        bg-primary/[0.055]
                        blur-3xl
                    "
                ></div>


                <div class="relative">
                    <div
                        class="
                            flex size-10
                            items-center justify-center

                            rounded-xl
                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.refresh-cw"
                            class="!size-5 stroke-[1.8]"
                        />
                    </div>


                    <h3
                        class="
                            mt-5

                            text-lg font-semibold
                            text-base-content
                        "
                    >
                        عمر سرویس، تحت کنترل
                    </h3>


                    <p
                        class="
                            mt-2
                            max-w-xl

                            text-sm
                            leading-7
                            text-base-content/50
                        "
                    >
                        زمان باقی‌مانده را ببینید، VPS را تمدید کنید و پیش از
                        پایان سرویس اعلان دریافت کنید.
                    </p>


                    <div
                        class="
                            mt-7

                            rounded-2xl

                            border border-primary/10
                            bg-base-100/90

                            p-4
                        "
                    >
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-4
                            "
                        >
                            <div>
                                <div
                                    class="
                                        text-[10px]
                                        text-base-content/40
                                    "
                                >
                                    زمان باقی‌مانده سرویس
                                </div>

                                <div
                                    class="
                                        mt-1

                                        text-base font-semibold
                                        text-base-content
                                    "
                                >
                                    ۲۳ روز
                                </div>
                            </div>

                            <span
                                class="
                                    inline-flex
                                    items-center gap-1.5

                                    rounded-full
                                    bg-success/10

                                    px-3 py-1.5

                                    text-xs font-medium
                                    text-success
                                "
                            >
                                <span
                                    class="
                                        size-1.5
                                        rounded-full
                                        bg-success
                                    "
                                ></span>

                                فعال
                            </span>
                        </div>


                        <div
                            class="
                                mt-4

                                grid gap-2

                                sm:grid-cols-3
                            "
                        >
                            <div
                                class="
                                    flex items-center gap-2

                                    rounded-xl
                                    bg-base-200/60

                                    px-3 py-2.5
                                "
                            >
                                <x-icon
                                    name="lucide.refresh-cw"
                                    class="
                                        !size-3.5
                                        shrink-0
                                        text-primary
                                        stroke-[1.8]
                                    "
                                />

                                <span
                                    class="
                                        text-[11px]
                                        text-base-content/60
                                    "
                                >
                                    تمدید سرویس
                                </span>
                            </div>


                            <div
                                class="
                                    flex items-center gap-2

                                    rounded-xl
                                    bg-base-200/60

                                    px-3 py-2.5
                                "
                            >
                                <x-icon
                                    name="lucide.bell"
                                    class="
                                        !size-3.5
                                        shrink-0
                                        text-primary
                                        stroke-[1.8]
                                    "
                                />

                                <span
                                    class="
                                        text-[11px]
                                        text-base-content/60
                                    "
                                >
                                    هشدار پیش از انقضا
                                </span>
                            </div>


                            <div
                                class="
                                    flex items-center gap-2

                                    rounded-xl
                                    bg-base-200/60

                                    px-3 py-2.5
                                "
                            >
                                <x-icon
                                    name="lucide.shield-check"
                                    class="
                                        !size-3.5
                                        shrink-0
                                        text-primary
                                        stroke-[1.8]
                                    "
                                />

                                <span
                                    class="
                                        text-[11px]
                                        text-base-content/60
                                    "
                                >
                                    پایان امن سرویس
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>


            {{-- Services & Containers --}}
            <article
                class="
                    rounded-2xl

                    border border-base-300/80
                    bg-base-100

                    p-5

                    transition-all
                    duration-200

                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.025]

                    sm:p-6

                    lg:col-span-12
                "
            >
                <div
                    class="
                        grid gap-6

                        lg:grid-cols-[0.75fr_1.25fr]
                        lg:items-center
                    "
                >
                    <div>
                        <div
                            class="
                                flex size-10
                                items-center justify-center

                                rounded-xl
                                bg-primary/10
                                text-primary
                            "
                        >
                            <x-icon
                                name="lucide.layers"
                                class="!size-5 stroke-[1.8]"
                            />
                        </div>


                        <h3
                            class="
                                mt-5

                                text-lg font-semibold
                                text-base-content
                            "
                        >
                            وضعیت سرویس‌ها و کانتینرها، بدون ابهام
                        </h3>


                        <p
                            class="
                                mt-2

                                max-w-md

                                text-sm
                                leading-7
                                text-base-content/50
                            "
                        >
                            سرویس‌های Linux و کانتینرهای Docker جداگانه نمایش داده
                            می‌شوند تا بدانید دقیقاً چه چیزی روی سرور در حال اجراست.
                        </p>
                    </div>


                    <div
                        class="
                            grid gap-3

                            sm:grid-cols-2
                        "
                    >
                        {{-- Linux service --}}
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3

                                rounded-xl
                                bg-base-200/60

                                px-4 py-3.5
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-center gap-2.5
                                "
                            >
                                <span
                                    class="
                                        flex size-8
                                        items-center justify-center

                                        rounded-lg
                                        bg-base-100
                                        text-base-content/50
                                    "
                                >
                                    <x-icon
                                        name="lucide.settings-2"
                                        class="!size-4 stroke-[1.7]"
                                    />
                                </span>

                                <div>
                                    <div
                                        class="
                                            text-[10px]
                                            text-base-content/35
                                        "
                                    >
                                        Linux Service
                                    </div>

                                    <div
                                        dir="ltr"
                                        class="
                                            mt-0.5

                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        ssh.service
                                    </div>
                                </div>
                            </div>

                            <span
                                class="
                                    inline-flex
                                    items-center gap-1.5

                                    text-[10px] font-medium
                                    text-success
                                "
                            >
                                <span
                                    class="
                                        size-1.5
                                        rounded-full
                                        bg-success
                                    "
                                ></span>

                                Running
                            </span>
                        </div>


                        {{-- Docker container --}}
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3

                                rounded-xl
                                bg-base-200/60

                                px-4 py-3.5
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-center gap-2.5
                                "
                            >
                                <span
                                    class="
                                        flex size-8
                                        items-center justify-center

                                        rounded-lg
                                        bg-base-100
                                        text-base-content/50
                                    "
                                >
                                    <x-icon
                                        name="lucide.container"
                                        class="!size-4 stroke-[1.7]"
                                    />
                                </span>

                                <div>
                                    <div
                                        class="
                                            text-[10px]
                                            text-base-content/35
                                        "
                                    >
                                        Docker Container
                                    </div>

                                    <div
                                        dir="ltr"
                                        class="
                                            mt-0.5

                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        application
                                    </div>
                                </div>
                            </div>

                            <span
                                class="
                                    inline-flex
                                    items-center gap-1.5

                                    text-[10px] font-medium
                                    text-success
                                "
                            >
                                <span
                                    class="
                                        size-1.5
                                        rounded-full
                                        bg-success
                                    "
                                ></span>

                                Running
                            </span>
                        </div>
                    </div>
                </div>
            </article>
        </div>


        {{-- Closing statement --}}
        <div
            class="
                mx-auto
                mt-10
                max-w-2xl
                text-center
            "
        >
            <p
                class="
                    text-sm
                    leading-7
                    text-base-content/45
                "
            >
                Coreflare جزئیات فنی را در زمان لازم نشان می‌دهد؛
                تمرکز اصلی همیشه روی وضعیت واقعی و اقدام بعدی شماست.
            </p>
        </div>
    </div>
</section>
