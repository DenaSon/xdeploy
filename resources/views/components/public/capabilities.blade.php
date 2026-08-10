<section
    id="capabilities"
    class="
        relative
        border-b border-base-300/60
        bg-base-100
    "
>
    <div
        class="
            mx-auto w-full max-w-7xl
            px-4 py-20
            sm:px-6 sm:py-24
            lg:px-8 lg:py-28
        "
    >

        {{-- Heading --}}
        <div
            class="
                mx-auto max-w-2xl
                text-center
            "
        >
            <span
                class="
                    inline-flex items-center gap-2

                    rounded-full
                    border border-primary/15
                    bg-primary/[0.06]

                    px-3 py-1.5

                    text-xs font-medium
                    text-primary
                "
            >
                <x-icon
                    name="lucide.layout-grid"
                    class="!size-3.5 stroke-[1.8]"
                />

                مدیریت یکپارچه
            </span>

            <h2
                class="
                    mt-5
                    text-3xl font-semibold
                    leading-[1.4]
                    tracking-tight
                    text-base-content

                    sm:text-4xl
                "
            >
                آنچه برای مدیریت سرور نیاز داری،

                <span class="text-primary">
                    در یک محیط
                </span>
            </h2>

            <p
                class="
                    mx-auto mt-4
                    max-w-xl

                    text-sm leading-7
                    text-base-content/55

                    sm:text-base sm:leading-8
                "
            >
                از وضعیت و منابع سرور تا سرویس‌ها، کانتینرها و برنامه‌ها؛
                xDeploy اطلاعات مهم را واضح و بدون پیچیدگی غیرضروری در اختیار تو قرار می‌دهد.
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

            {{-- Server --}}
            <article
                class="
                    group
                    relative
                    overflow-hidden

                    rounded-2xl
                    border border-base-300
                    bg-base-200/40

                    p-5

                    transition-colors duration-200
                    hover:border-primary/20

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
                        bg-primary/[0.06]
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
                        وضعیت سرور، واضح و قابل فهم
                    </h3>

                    <p
                        class="
                            mt-2 max-w-md
                            text-sm leading-7
                            text-base-content/50
                        "
                    >
                        اطلاعات اصلی سرور و آماده بودن آن برای مدیریت را
                        بدون بررسی دستی چندین ابزار مختلف ببین.
                    </p>


                    {{-- Server preview --}}
                    <div
                        class="
                            mt-7
                            rounded-2xl

                            border border-base-300
                            bg-base-100

                            p-4
                        "
                    >
                        <div
                            class="
                                flex flex-col gap-4

                                sm:flex-row
                                sm:items-center
                                sm:justify-between
                            "
                        >
                            <div class="flex min-w-0 items-center gap-3">

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
                                            technical-value
                                            mt-1
                                            text-[11px]
                                            text-base-content/40
                                        "
                                    >
                                        185.000.000.24
                                    </div>

                                </div>
                            </div>


                            <div
                                class="
                                    inline-flex w-fit
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
                            </div>
                        </div>


                        <div
                            class="
                                mt-4
                                grid grid-cols-2 gap-2

                                sm:grid-cols-4
                            "
                        >
                            <div class="rounded-xl bg-base-200/60 p-3">
                                <div class="text-[10px] text-base-content/40">
                                    سیستم‌عامل
                                </div>

                                <div class="mt-1 text-xs font-medium text-base-content/75">
                                    Ubuntu 24.04
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-200/60 p-3">
                                <div class="text-[10px] text-base-content/40">
                                    اتصال
                                </div>

                                <div class="mt-1 text-xs font-medium text-success">
                                    متصل
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-200/60 p-3">
                                <div class="text-[10px] text-base-content/40">
                                    CPU
                                </div>

                                <div
                                    dir="ltr"
                                    class="mt-1 text-xs font-medium text-base-content/75"
                                >
                                    2 Core
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-200/60 p-3">
                                <div class="text-[10px] text-base-content/40">
                                    RAM
                                </div>

                                <div
                                    dir="ltr"
                                    class="mt-1 text-xs font-medium text-base-content/75"
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
                    border border-base-300
                    bg-base-100

                    p-5

                    transition-colors duration-200
                    hover:border-primary/20

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
                    مانیتورینگ بدون شلوغی
                </h3>

                <p
                    class="
                        mt-2
                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    مهم‌ترین منابع و شاخص‌های سرور را در یک نگاه ببین.
                </p>


                <div
                    class="
                        mt-7
                        grid grid-cols-2 gap-3
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
                                    flex items-center
                                    justify-between gap-2
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


            {{-- Services & Containers --}}
            <article
                class="
                    rounded-2xl
                    border border-base-300
                    bg-base-100

                    p-5

                    transition-colors duration-200
                    hover:border-primary/20

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
                    سرویس‌ها و کانتینرها، هرکدام در جای خود
                </h3>

                <p
                    class="
                        mt-2
                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    سرویس‌های Linux و کانتینرهای Docker مستقل از یکدیگر
                    نمایش داده می‌شوند تا وضعیت Runtime واضح باقی بماند.
                </p>


                <div class="mt-6 space-y-2">

                    {{-- Linux service --}}
                    <div
                        class="
                            flex items-center
                            justify-between

                            rounded-xl
                            bg-base-200/60

                            px-3.5 py-3
                        "
                    >
                        <div class="flex items-center gap-2.5">

                            <span
                                class="
                                    flex size-7
                                    items-center justify-center

                                    rounded-lg
                                    bg-base-100
                                    text-base-content/45
                                "
                            >
                                <x-icon
                                    name="lucide.settings-2"
                                    class="!size-3.5 stroke-[1.7]"
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
                                inline-flex items-center gap-1.5

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


                    {{-- Container --}}
                    <div
                        class="
                            flex items-center
                            justify-between

                            rounded-xl
                            bg-base-200/60

                            px-3.5 py-3
                        "
                    >
                        <div class="flex items-center gap-2.5">

                            <span
                                class="
                                    flex size-7
                                    items-center justify-center

                                    rounded-lg
                                    bg-base-100
                                    text-base-content/45
                                "
                            >
                                <x-icon
                                    name="lucide.container"
                                    class="!size-3.5 stroke-[1.7]"
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
                                inline-flex items-center gap-1.5

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
            </article>


            {{-- Applications --}}
            <article
                class="
                    relative
                    overflow-hidden

                    rounded-2xl
                    border border-primary/15
                    bg-primary/[0.045]

                    p-5

                    transition-colors duration-200
                    hover:border-primary/25

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
                        برنامه را از نصب تا آماده استفاده مدیریت کن
                    </h3>

                    <p
                        class="
                            mt-2 max-w-xl
                            text-sm leading-7
                            text-base-content/50
                        "
                    >
                        برای برنامه‌های پشتیبانی‌شده، xDeploy فقط نصب را اجرا نمی‌کند؛
                        وضعیت اجرا، مراحل آماده‌سازی و آماده بودن برنامه برای استفاده را نیز دنبال می‌کند.
                    </p>


                    {{-- Application preview --}}
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
                                flex flex-col gap-4

                                sm:flex-row
                                sm:items-center
                                sm:justify-between
                            "
                        >
                            <div class="flex items-center gap-3">

                                <span
                                    class="
                                        flex size-11 shrink-0
                                        items-center justify-center

                                        rounded-xl
                                        bg-primary/10
                                        text-primary
                                    "
                                >
                                    <x-icon
                                        name="lucide.package"
                                        class="!size-5 stroke-[1.8]"
                                    />
                                </span>


                                <div>
                                    <div
                                        class="
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        برنامه
                                    </div>

                                    <div
                                        class="
                                            mt-1
                                            text-[11px]
                                            text-base-content/45
                                        "
                                    >
                                        نصب شده و آماده استفاده
                                    </div>
                                </div>
                            </div>


                            <span
                                class="
                                    inline-flex w-fit
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

                                آماده
                            </span>
                        </div>


                        <div
                            class="
                                mt-4
                                grid grid-cols-2 gap-2

                                sm:grid-cols-4
                            "
                        >
                            @foreach([
                                'نصب',
                                'اجرا',
                                'پیکربندی',
                                'آماده',
                            ] as $label)

                                <div
                                    class="
                                        flex items-center gap-2

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

        </div>


        {{-- Closing statement --}}
        <div
            class="
                mx-auto mt-10
                max-w-xl
                text-center
            "
        >
            <p
                class="
                    text-sm leading-7
                    text-base-content/45
                "
            >
                جزئیات فنی زمانی نمایش داده می‌شوند که به آن‌ها نیاز داری؛
                نه قبل از آن.
            </p>
        </div>

    </div>
</section>
