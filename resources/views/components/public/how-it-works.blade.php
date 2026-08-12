<section
    id="how-it-works"
    class="
        relative
        border-b border-base-300/60
        bg-base-200/40
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
                    bg-primary/[0.06]

                    px-3 py-1.5

                    text-xs font-medium
                    text-primary
                "
            >
                <x-icon
                    name="lucide.workflow"
                    class="!size-3.5 stroke-[1.8]"
                />

                نحوه عملکرد xDeploy
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
                از اتصال تا راه‌اندازی برنامه،

                <span class="text-primary">
                    در یک فرآیند مشخص
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
                xDeploy مراحل اتصال، بررسی، آماده‌سازی و مدیریت سرور
                را در یک فرآیند یکپارچه ارائه می‌کند تا راه‌اندازی
                برنامه‌های پشتیبانی‌شده با کنترل و شفافیت بیشتری
                انجام شود.
            </p>
        </div>


        {{-- Steps --}}
        <div
            class="
                relative
                mt-14

                grid gap-4

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
                    bg-base-300

                    lg:block
                "
            ></div>


            {{-- Step 01 --}}
            <article
                class="
                    relative

                    rounded-2xl

                    border border-base-300
                    bg-base-100

                    p-5

                    transition-colors duration-200

                    hover:border-primary/20
                "
            >
                <div
                    class="
                        relative z-10

                        flex items-center
                        justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300
                            bg-base-100

                            text-primary
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
                            text-xs font-medium
                            text-base-content/30
                        "
                    >
                        01
                    </span>
                </div>

                <h3
                    class="
                        mt-6

                        text-base font-semibold
                        text-base-content
                    "
                >
                    اتصال یا تهیه VPS
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    VPS موجود خود را به xDeploy متصل کنید یا
                    مستقیماً یک سرور جدید تهیه کنید.
                </p>
            </article>


            {{-- Step 02 --}}
            <article
                class="
                    relative

                    rounded-2xl

                    border border-base-300
                    bg-base-100

                    p-5

                    transition-colors duration-200

                    hover:border-primary/20
                "
            >
                <div
                    class="
                        relative z-10

                        flex items-center
                        justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300
                            bg-base-100

                            text-primary
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
                            text-xs font-medium
                            text-base-content/30
                        "
                    >
                        02
                    </span>
                </div>

                <h3
                    class="
                        mt-6

                        text-base font-semibold
                        text-base-content
                    "
                >
                    بررسی و آماده‌سازی سرور
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    اتصال SSH، سیستم‌عامل و سطح دسترسی سرور
                    بررسی می‌شود تا محیط برای عملیات موردنیاز
                    آماده باشد.
                </p>
            </article>


            {{-- Step 03 --}}
            <article
                class="
                    relative

                    rounded-2xl

                    border border-base-300
                    bg-base-100

                    p-5

                    transition-colors duration-200

                    hover:border-primary/20
                "
            >
                <div
                    class="
                        relative z-10

                        flex items-center
                        justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            border border-base-300
                            bg-base-100

                            text-primary
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
                            text-xs font-medium
                            text-base-content/30
                        "
                    >
                        03
                    </span>
                </div>

                <h3
                    class="
                        mt-6

                        text-base font-semibold
                        text-base-content
                    "
                >
                    نصب و راه‌اندازی برنامه
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    برنامه موردنظر از میان گزینه‌های پشتیبانی‌شده
                    انتخاب می‌شود و xDeploy مراحل نصب و آماده‌سازی
                    آن را مدیریت می‌کند.
                </p>
            </article>


            {{-- Step 04 --}}
            <article
                class="
                    relative

                    rounded-2xl

                    border border-primary/15
                    bg-primary/[0.045]

                    p-5

                    transition-colors duration-200

                    hover:border-primary/25
                "
            >
                <div
                    class="
                        relative z-10

                        flex items-center
                        justify-between
                    "
                >
                    <span
                        class="
                            flex size-14
                            items-center justify-center

                            rounded-2xl

                            bg-primary
                            text-primary-content
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
                            text-xs font-medium
                            text-primary/50
                        "
                    >
                        04
                    </span>
                </div>

                <h3
                    class="
                        mt-6

                        text-base font-semibold
                        text-base-content
                    "
                >
                    پایش و مدیریت مستمر
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    وضعیت سرور، منابع، سرویس‌ها، کانتینرها و
                    برنامه‌های نصب‌شده از یک محیط واحد قابل
                    مشاهده و مدیریت هستند.
                </p>
            </article>

        </div>


        {{-- Journey summary --}}
        <div
            class="
                mt-8

                flex flex-wrap
                items-center justify-center

                gap-x-3 gap-y-2

                text-xs
                text-base-content/40
            "
        >
            <span>VPS</span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span>بررسی و آماده‌سازی</span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span>Application</span>

            <x-icon
                name="lucide.chevron-left"
                class="!size-3.5 stroke-[1.5]"
            />

            <span class="font-medium text-success">
                آماده مدیریت
            </span>
        </div>

    </div>
</section>
