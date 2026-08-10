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

                مسیر استفاده
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
                از سرور تا برنامه آماده،

                <span class="text-primary">
                    مرحله‌به‌مرحله
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
                xDeploy مسیر آماده‌سازی و مدیریت سرور را به چند مرحله روشن تبدیل می‌کند؛
                تا بدون درگیر شدن با پیچیدگی‌های غیرضروری زیرساخت،
                روی چیزی که می‌خواهی اجرا کنی تمرکز داشته باشی.
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
                    سرور را اضافه کن
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    VPS فعلی خودت را متصل کن یا از داخل xDeploy
                    یک سرور جدید تهیه کن.
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
                    آماده بودن سرور بررسی می‌شود
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    اتصال، سیستم‌عامل و دسترسی‌های لازم بررسی می‌شوند
                    تا سرور برای عملیات xDeploy آماده باشد.
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
                    برنامه را راه‌اندازی کن
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    یکی از برنامه‌های پشتیبانی‌شده را انتخاب کن؛
                    xDeploy مراحل نصب و آماده‌سازی آن را مدیریت می‌کند.
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
                    مدیریت را ادامه بده
                </h3>

                <p
                    class="
                        mt-2

                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    وضعیت سرور، منابع، سرویس‌ها، کانتینرها و برنامه‌ها را
                    از یک محیط واحد مشاهده و مدیریت کن.
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

            <span>آماده‌سازی</span>

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
