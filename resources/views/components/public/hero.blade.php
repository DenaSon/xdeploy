<section
    class="
        relative
        isolate
        overflow-hidden
        border-b border-base-300/60
    "
>
    {{-- Background atmosphere --}}
    <div
        aria-hidden="true"
        class="
            pointer-events-none
            absolute inset-0
            -z-10
            overflow-hidden
        "
    >
        <div
            class="
                absolute
                -top-40 start-1/2
                size-[44rem]
                -translate-x-1/2
                rounded-full
                bg-primary/[0.07]
                blur-3xl
            "
        ></div>

        <div
            class="
                absolute
                bottom-[-18rem] end-[-12rem]
                size-[34rem]
                rounded-full
                bg-accent/[0.04]
                blur-3xl
            "
        ></div>

        {{-- Subtle grid --}}
        <div
            class="
                absolute inset-0
                opacity-[0.025]
                dark:opacity-[0.04]
            "
            style="
                background-image:
                    linear-gradient(to right, currentColor 1px, transparent 1px),
                    linear-gradient(to bottom, currentColor 1px, transparent 1px);
                background-size: 48px 48px;
            "
        ></div>
    </div>

    <div
        class="
            mx-auto
            grid min-h-[calc(100svh-4rem)]
            w-full max-w-7xl
            items-center
            gap-14

            px-4 py-16

            sm:px-6 sm:py-20

            lg:grid-cols-[0.92fr_1.08fr]
            lg:gap-16
            lg:px-8 lg:py-24
        "
    >
        {{-- Copy --}}
        <div
            class="
                mx-auto
                max-w-2xl
                text-center

                lg:mx-0
                lg:text-start
            "
        >
            {{-- Eyebrow --}}
            <div
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
                <span
                    class="
                        size-1.5
                        rounded-full
                        bg-success
                    "
                ></span>

                مدیریت یکپارچه VPS و سرویس‌ها
            </div>

            {{-- Heading --}}
            <h1
                class="
                    mt-6

                    text-4xl font-semibold
                    leading-[1.35]
                    tracking-tight
                    text-base-content

                    sm:text-5xl
                    sm:leading-[1.3]

                    lg:text-[3.5rem]
                "
            >
                از تهیه VPS تا راه‌اندازی سرویس،

                <span class="text-primary">
                    یک مسیر یکپارچه برای مدیریت سرور.
                </span>
            </h1>

            {{-- Description --}}
            <p
                class="
                    mx-auto mt-6
                    max-w-xl

                    text-base
                    leading-8
                    text-base-content/60

                    sm:text-lg
                    sm:leading-9

                    lg:mx-0
                "
            >
                VPS موجود خود را متصل کنید یا مستقیماً یک سرور جدید
                تهیه کنید. xDeploy آماده‌سازی سرور، پایش منابع و نصب
                و مدیریت برنامه‌های پشتیبانی‌شده را در یک پنل
                یکپارچه در اختیار شما قرار می‌دهد.
            </p>

            {{-- Actions --}}
            <div
                class="
                    mt-8 flex
                    flex-col
                    items-stretch
                    justify-center
                    gap-3

                    sm:flex-row
                    sm:items-center

                    lg:justify-start
                "
            >
                <x-button
                    label="شروع"
                    icon="lucide.arrow-left"
                    :link="route('login')"
                    wire:navigate
                    class="
                        btn-primary btn-lg
                        rounded-xl
                        px-6
                        font-medium
                    "
                />

                <a
                    href="#how-it-works"
                    class="
                        btn btn-ghost btn-lg
                        rounded-xl

                        px-5

                        font-normal
                        text-base-content/65

                        hover:bg-base-200
                        hover:text-base-content
                    "
                >
                    <span>
                        نحوه عملکرد
                    </span>

                    <x-icon
                        name="lucide.chevron-down"
                        class="!size-4 stroke-[1.7]"
                    />
                </a>
            </div>

            {{-- Capability line --}}
            <div
                class="
                    mt-8
                    flex flex-wrap
                    items-center
                    justify-center
                    gap-x-5 gap-y-2

                    text-xs
                    text-base-content/45

                    lg:justify-start
                "
            >
                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.server"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    اتصال VPS موجود
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.cloud"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    تهیه VPS جدید
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.activity"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    پایش سرور
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.package-check"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    نصب و مدیریت برنامه‌ها
                </span>
            </div>
        </div>

        {{-- Product visual --}}
        <div
            class="
                relative
                mx-auto
                w-full max-w-xl

                lg:max-w-none
            "
        >
            {{-- Ambient glow --}}
            <div
                aria-hidden="true"
                class="
                    absolute
                    inset-12
                    rounded-full
                    bg-primary/10
                    blur-3xl
                "
            ></div>

            {{-- Product diagram --}}
            <div
                class="
                    relative
                    overflow-hidden

                    rounded-[1.75rem]

                    border border-base-300
                    bg-base-100/90

                    p-4

                    shadow-[0_24px_80px_rgba(15,23,42,0.07)]
                    backdrop-blur-xl

                    sm:p-6
                "
            >
                {{-- Top bar --}}
                <div
                    class="
                        flex
                        items-center justify-between

                        border-b border-base-300/70
                        pb-4
                    "
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="
                                flex size-8
                                items-center justify-center

                                rounded-lg
                                bg-primary/10
                                text-primary
                            "
                        >
                            <x-icon
                                name="lucide.workflow"
                                class="!size-4 stroke-[1.8]"
                            />
                        </span>

                        <div>
                            <div
                                class="
                                    text-xs font-medium
                                    text-base-content/75
                                "
                            >
                                فرآیند مدیریت
                            </div>

                            <div
                                dir="ltr"
                                class="
                                    mt-0.5
                                    text-[10px]
                                    text-base-content/40
                                "
                            >
                                Server → Application
                            </div>
                        </div>
                    </div>

                    <span
                        class="
                            inline-flex
                            items-center gap-1.5

                            rounded-full
                            bg-success/10

                            px-2 py-1

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

                        آماده
                    </span>
                </div>

                {{-- Flow --}}
                <div class="py-6">
                    {{-- Server → xDeploy --}}
                    <div
                        class="
                            grid
                            grid-cols-[1fr_auto_1fr]
                            items-center
                            gap-3
                        "
                    >
                        {{-- VPS --}}
                        <div
                            class="
                                rounded-2xl
                                border border-base-300
                                bg-base-200/55
                                p-4
                            "
                        >
                            <div
                                class="
                                    flex size-9
                                    items-center justify-center

                                    rounded-xl
                                    bg-base-100
                                    text-base-content/70

                                    ring-1 ring-base-300
                                "
                            >
                                <x-icon
                                    name="lucide.server"
                                    class="!size-[18px] stroke-[1.7]"
                                />
                            </div>

                            <div
                                class="
                                    mt-4
                                    text-sm font-medium
                                    text-base-content
                                "
                            >
                                VPS
                            </div>

                            <div
                                class="
                                    mt-1
                                    text-[11px]
                                    text-base-content/45
                                "
                            >
                                سرور موجود یا جدید
                            </div>
                        </div>

                        {{-- Connection --}}
                        <div
                            class="
                                flex items-center
                                text-base-content/20
                            "
                        >
                            <span
                                class="
                                    hidden h-px w-5
                                    bg-base-300
                                    sm:block
                                "
                            ></span>

                            <x-icon
                                name="lucide.chevron-left"
                                class="!size-4 stroke-[1.5]"
                            />
                        </div>

                        {{-- xDeploy --}}
                        <div
                            class="
                                relative
                                overflow-hidden

                                rounded-2xl
                                border border-primary/20
                                bg-primary/[0.06]

                                p-4
                            "
                        >
                            <div
                                aria-hidden="true"
                                class="
                                    absolute
                                    -end-8 -top-8
                                    size-20
                                    rounded-full
                                    bg-primary/10
                                    blur-2xl
                                "
                            ></div>

                            <div
                                class="
                                    relative
                                    flex size-9
                                    items-center justify-center

                                    rounded-xl
                                    bg-primary
                                    text-primary-content
                                "
                            >
                                <x-icon
                                    name="lucide.layers-3"
                                    class="!size-[18px] stroke-[1.7]"
                                />
                            </div>

                            <div
                                class="
                                    relative
                                    mt-4
                                    text-sm font-semibold
                                    text-primary
                                "
                            >
                                xDeploy
                            </div>

                            <div
                                class="
                                    relative
                                    mt-1
                                    text-[11px]
                                    text-base-content/45
                                "
                            >
                                آماده‌سازی، استقرار و مدیریت
                            </div>
                        </div>
                    </div>

                    {{-- Vertical connector --}}
                    <div
                        class="
                            flex h-10
                            items-center justify-center
                        "
                    >
                        <div
                            class="
                                relative
                                h-full w-px
                                bg-base-300
                            "
                        >
                            <span
                                class="
                                    absolute
                                    -bottom-0.5 start-1/2
                                    -translate-x-1/2

                                    text-base-content/25
                                "
                            >
                                <x-icon
                                    name="lucide.chevron-down"
                                    class="!size-4 stroke-[1.5]"
                                />
                            </span>
                        </div>
                    </div>

                    {{-- Application --}}
                    <div
                        class="
                            rounded-2xl
                            border border-base-300
                            bg-base-100
                            p-4
                        "
                    >
                        <div
                            class="
                                flex
                                items-center justify-between
                                gap-3
                            "
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="
                                        flex size-10 shrink-0
                                        items-center justify-center

                                        rounded-xl
                                        bg-primary/10
                                        text-primary
                                    "
                                >
                                    <x-icon
                                        name="lucide.package"
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
                                        برنامه
                                    </div>

                                    <div
                                        class="
                                            mt-1
                                            text-[11px]
                                            text-base-content/45
                                        "
                                    >
                                        نصب، اجرا و مدیریت
                                    </div>
                                </div>
                            </div>

                            <div
                                class="
                                    flex shrink-0
                                    items-center gap-1.5

                                    rounded-full
                                    bg-success/10

                                    px-2.5 py-1.5

                                    text-[10px] font-medium
                                    text-success
                                "
                            >
                                <x-icon
                                    name="lucide.circle-check"
                                    class="!size-3.5 stroke-[1.8]"
                                />

                                آماده
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bottom status --}}
                <div
                    class="
                        grid grid-cols-3
                        gap-2

                        border-t border-base-300/70
                        pt-4
                    "
                >
                    <div
                        class="
                            rounded-xl
                            bg-base-200/60
                            px-3 py-2.5
                        "
                    >
                        <div
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            سرور
                        </div>

                        <div
                            class="
                                mt-1
                                flex items-center gap-1.5

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

                            آماده
                        </div>
                    </div>

                    <div
                        class="
                            rounded-xl
                            bg-base-200/60
                            px-3 py-2.5
                        "
                    >
                        <div
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            برنامه
                        </div>

                        <div
                            class="
                                mt-1
                                text-xs font-medium
                                text-success
                            "
                        >
                            Running
                        </div>
                    </div>

                    <div
                        class="
                            rounded-xl
                            bg-base-200/60
                            px-3 py-2.5
                        "
                    >
                        <div
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            مدیریت
                        </div>

                        <div
                            class="
                                mt-1
                                text-xs font-medium
                                text-primary
                            "
                        >
                            xDeploy
                        </div>
                    </div>
                </div>
            </div>

            {{-- Floating status --}}
            <div
                class="
                    absolute
                    -bottom-5 start-6

                    hidden
                    items-center gap-2

                    rounded-xl

                    border border-base-300
                    bg-base-100/95

                    px-3 py-2.5

                    shadow-lg
                    shadow-base-content/[0.04]

                    backdrop-blur-xl

                    sm:flex
                "
            >
                <span
                    class="
                        flex size-7
                        items-center justify-center

                        rounded-lg
                        bg-success/10
                        text-success
                    "
                >
                    <x-icon
                        name="lucide.check"
                        class="!size-3.5 stroke-[2]"
                    />
                </span>

                <div>
                    <div
                        class="
                            text-[10px]
                            text-base-content/40
                        "
                    >
                        وضعیت سیستم
                    </div>


                    <div
                        class="
                            mt-0.5
                            text-xs font-medium
                            text-base-content/75
                        "
                    >
                        آماده استفاده
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
