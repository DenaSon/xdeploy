@php
    $productName = config('app.name');
@endphp

<section
    id="how-it-works"
    class="
        relative
        scroll-mt-24
        overflow-hidden

        border-b border-base-300/60
        bg-base-200/35
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
                -start-40 top-20
                size-[28rem]
                rounded-full
                bg-primary/[0.035]
                blur-3xl
            "
        ></div>

        <div
            class="
                absolute
                -end-48 bottom-0
                size-[32rem]
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
        {{-- Section heading --}}
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
                    name="lucide.workflow"
                    class="!size-3.5 stroke-[1.8]"
                />

                نحوه عملکرد
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
                از آماده‌سازی سرور تا اجرای برنامه،

                <span class="text-primary">
                    در یک مسیر مشخص و قابل مدیریت
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
                مراحل اتصال یا تهیه سرور، بررسی پیش‌نیازها،
                نصب برنامه و مدیریت سرویس‌ها در یک فرآیند
                یکپارچه انجام می‌شود.
            </p>
        </div>


        {{-- Steps --}}
        <div
            x-data="{ visible: false }"
            x-intersect.once="visible = true"
            class="
                relative
                mt-14

                grid
                gap-4

                md:grid-cols-2

                lg:mt-16
                lg:grid-cols-4
                lg:gap-5
            "
        >
            {{-- Desktop connector --}}
            <div
                aria-hidden="true"
                class="
                    pointer-events-none

                    absolute
                    start-[12.5%]
                    end-[12.5%]
                    top-7

                    hidden h-px

                    bg-gradient-to-l
                    from-base-300/30
                    via-base-300
                    to-base-300/30

                    lg:block
                "
            ></div>


            {{-- Step 01 --}}
            <article
                :class="visible
                    ? 'opacity-100 translate-y-0'
                    : 'opacity-0 translate-y-3'"
                class="
                    group
                    relative

                    rounded-2xl

                    border border-base-300/80
                    bg-base-100/85

                    p-5

                    shadow-sm
                    shadow-base-content/[0.015]

                    backdrop-blur-sm

                    transition-all
                    duration-500
                    ease-out

                    hover:-translate-y-0.5
                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.035]
                "
            >
                <div
                    class="
                        relative z-10

                        flex
                        items-center justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300/80
                            bg-base-100

                            text-primary

                            shadow-sm
                            shadow-base-content/[0.025]

                            transition-colors
                            duration-200

                            group-hover:border-primary/15
                            group-hover:bg-primary/[0.05]
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-6 stroke-[1.7]"
                        />
                    </span>

                    <span
                        dir="ltr"
                        class="
                            font-mono
                            text-[11px]
                            font-medium
                            text-base-content/25
                        "
                    >
                        01
                    </span>
                </div>


                <h3
                    class="
                        mt-6

                        text-base
                        font-semibold
                        text-base-content
                    "
                >
                    اتصال یا تهیه VPS
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    VPS موجود خود را متصل کنید یا از داخل
                    سامانه، یک سرور جدید متناسب با نیاز خود
                    تهیه کنید.
                </p>
            </article>


            {{-- Step 02 --}}
            <article
                :class="visible
                    ? 'opacity-100 translate-y-0'
                    : 'opacity-0 translate-y-3'"
                class="
                    group
                    relative

                    rounded-2xl

                    border border-base-300/80
                    bg-base-100/85

                    p-5

                    shadow-sm
                    shadow-base-content/[0.015]

                    backdrop-blur-sm

                    transition-all
                    delay-75
                    duration-500
                    ease-out

                    hover:-translate-y-0.5
                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.035]
                "
            >
                <div
                    class="
                        relative z-10

                        flex
                        items-center justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300/80
                            bg-base-100

                            text-primary

                            shadow-sm
                            shadow-base-content/[0.025]

                            transition-colors
                            duration-200

                            group-hover:border-primary/15
                            group-hover:bg-primary/[0.05]
                        "
                    >
                        <x-icon
                            name="lucide.shield-check"
                            class="!size-6 stroke-[1.7]"
                        />
                    </span>

                    <span
                        dir="ltr"
                        class="
                            font-mono
                            text-[11px]
                            font-medium
                            text-base-content/25
                        "
                    >
                        02
                    </span>
                </div>


                <h3
                    class="
                        mt-6

                        text-base
                        font-semibold
                        text-base-content
                    "
                >
                    بررسی و آماده‌سازی سرور
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    اتصال SSH، سیستم‌عامل، سطح دسترسی و
                    پیش‌نیازهای عملیاتی بررسی می‌شوند تا
                    سرور برای ادامه فرآیند آماده باشد.
                </p>
            </article>


            {{-- Step 03 --}}
            <article
                :class="visible
                    ? 'opacity-100 translate-y-0'
                    : 'opacity-0 translate-y-3'"
                class="
                    group
                    relative

                    rounded-2xl

                    border border-base-300/80
                    bg-base-100/85

                    p-5

                    shadow-sm
                    shadow-base-content/[0.015]

                    backdrop-blur-sm

                    transition-all
                    delay-150
                    duration-500
                    ease-out

                    hover:-translate-y-0.5
                    hover:border-primary/20
                    hover:shadow-lg
                    hover:shadow-base-content/[0.035]
                "
            >
                <div
                    class="
                        relative z-10

                        flex
                        items-center justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300/80
                            bg-base-100

                            text-primary

                            shadow-sm
                            shadow-base-content/[0.025]

                            transition-colors
                            duration-200

                            group-hover:border-primary/15
                            group-hover:bg-primary/[0.05]
                        "
                    >
                        <x-icon
                            name="lucide.package-plus"
                            class="!size-6 stroke-[1.7]"
                        />
                    </span>

                    <span
                        dir="ltr"
                        class="
                            font-mono
                            text-[11px]
                            font-medium
                            text-base-content/25
                        "
                    >
                        03
                    </span>
                </div>


                <h3
                    class="
                        mt-6

                        text-base
                        font-semibold
                        text-base-content
                    "
                >
                    نصب و راه‌اندازی برنامه
                </h3>


                <p
                    class="
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    برنامه موردنظر را از میان گزینه‌های
                    پشتیبانی‌شده انتخاب کنید؛ نصب، پیش‌نیازها
                    و بررسی نهایی به‌صورت کنترل‌شده انجام می‌شوند.
                </p>
            </article>


            {{-- Step 04 --}}
            <article
                :class="visible
                    ? 'opacity-100 translate-y-0'
                    : 'opacity-0 translate-y-3'"
                class="
                    group
                    relative

                    overflow-hidden

                    rounded-2xl

                    border border-primary/15
                    bg-primary/[0.045]

                    p-5

                    shadow-sm
                    shadow-primary/[0.03]

                    transition-all
                    delay-200
                    duration-500
                    ease-out

                    hover:-translate-y-0.5
                    hover:border-primary/25
                    hover:shadow-lg
                    hover:shadow-primary/[0.06]
                "
            >
                {{-- Accent --}}
                <div
                    aria-hidden="true"
                    class="
                        absolute
                        -end-14 -top-14

                        size-32
                        rounded-full

                        bg-primary/[0.09]
                        blur-2xl
                    "
                ></div>


                <div
                    class="
                        relative z-10

                        flex
                        items-center justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            bg-primary
                            text-primary-content

                            shadow-lg
                            shadow-primary/15
                        "
                    >
                        <x-icon
                            name="lucide.layout-dashboard"
                            class="!size-6 stroke-[1.7]"
                        />
                    </span>

                    <span
                        dir="ltr"
                        class="
                            font-mono
                            text-[11px]
                            font-medium
                            text-primary/45
                        "
                    >
                        04
                    </span>
                </div>


                <h3
                    class="
                        relative
                        mt-6

                        text-base
                        font-semibold
                        text-base-content
                    "
                >
                    پایش و مدیریت
                </h3>


                <p
                    class="
                        relative
                        mt-2

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    وضعیت سرور، منابع، سرویس‌ها، کانتینرها و
                    برنامه‌های نصب‌شده از یک پنل واحد قابل
                    مشاهده و مدیریت هستند.
                </p>
            </article>
        </div>


        {{-- Journey summary --}}
        <div
            class="
                mx-auto
                mt-9

                flex w-fit
                max-w-full
                flex-wrap
                items-center justify-center

                gap-x-3 gap-y-2

                rounded-full
                border border-base-300/70
                bg-base-100/65

                px-4 py-2.5

                text-xs
                text-base-content/40

                backdrop-blur-sm
            "
        >
            <span>
                VPS
            </span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span>
                آماده‌سازی
            </span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span>
                نصب برنامه
            </span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span
                class="
                    inline-flex
                    items-center gap-1.5

                    font-medium
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
    </div>
</section>
