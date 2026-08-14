@php
    $productName = config('app.name');
    $productHost = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');
@endphp

<section
    class="
        relative isolate overflow-hidden
        border-b border-base-300/60
    "
>
    {{-- Ambient background --}}
    <div
        aria-hidden="true"
        class="
            pointer-events-none
            absolute inset-0 -z-10
            overflow-hidden
        "
    >
        {{-- Main glow --}}
        <div
            class="
                absolute
                -top-64 start-1/2
                size-[54rem]
                -translate-x-1/2
                rounded-full
                bg-primary/[0.08]
                blur-3xl
            "
        ></div>

        {{-- Secondary glow --}}
        <div
            class="
                absolute
                bottom-[-22rem] end-[-16rem]
                size-[40rem]
                rounded-full
                bg-accent/[0.045]
                blur-3xl
            "
        ></div>

        {{-- Soft grid --}}
        <div
            class="
                absolute inset-0
                opacity-[0.022]
                dark:opacity-[0.035]
            "
            style="
                background-image:
                    linear-gradient(to right, currentColor 1px, transparent 1px),
                    linear-gradient(to bottom, currentColor 1px, transparent 1px);
                background-size: 56px 56px;
                mask-image: linear-gradient(to bottom, black, transparent 92%);
            "
        ></div>
    </div>


    <div
        class="
            mx-auto
            grid
            min-h-[calc(100svh-4rem)]
            w-full max-w-7xl
            items-center
            gap-14

            px-4 py-16

            sm:px-6 sm:py-20

            lg:grid-cols-[0.88fr_1.12fr]
            lg:gap-20
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
                    bg-primary/[0.055]

                    px-3 py-1.5

                    text-xs font-medium
                    text-primary
                "
            >
                <span class="relative flex size-2">
                    <span
                        class="
                            absolute
                            inline-flex size-full
                            animate-ping
                            rounded-full
                            bg-success/60
                            opacity-60
                        "
                    ></span>

                    <span
                        class="
                            relative
                            inline-flex size-2
                            rounded-full
                            bg-success
                        "
                    ></span>
                </span>

                مدیریت یکپارچه VPS و برنامه‌های Self-hosted
            </div>


            {{-- Heading --}}
            <h1
                class="
                    mt-6

                    text-4xl
                    font-semibold
                    leading-[1.35]
                    tracking-tight
                    text-base-content

                    sm:text-5xl
                    sm:leading-[1.3]

                    lg:text-[3.55rem]
                "
            >
                از یک VPS خام،

                <span class="text-primary">
                    به یک سرویس آماده استفاده.
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
                VPS موجود خود را متصل کنید یا مستقیماً یک سرور جدید تهیه کنید.
                {{ $productName }} آماده‌سازی سرور، نصب برنامه‌ها، پایش منابع
                و مدیریت سرویس‌های پشتیبانی‌شده را در یک پنل یکپارچه
                در اختیار شما قرار می‌دهد.
            </p>


            {{-- Actions --}}
            <div
                class="
                    mt-8
                    flex flex-col
                    items-stretch
                    justify-center
                    gap-3

                    sm:flex-row
                    sm:items-center

                    lg:justify-start
                "
            >
                <x-button
                    label="شروع استفاده"
                    icon="lucide.arrow-left"
                    :link="route('login')"
                    wire:navigate
                    class="
                        btn-primary btn-lg
                        rounded-xl
                        px-6
                        font-medium

                        shadow-lg
                        shadow-primary/10
                    "
                />

                <a
                    href="#how-it-works"
                    @click.prevent="
                        document.querySelector('#how-it-works')
                            ?.scrollIntoView({
                                behavior: window.matchMedia(
                                    '(prefers-reduced-motion: reduce)'
                                ).matches
                                    ? 'auto'
                                    : 'smooth',
                                block: 'start'
                            })
                    "
                    class="
                        btn btn-ghost btn-lg
                        rounded-xl
                        px-5

                        font-normal
                        text-base-content/60

                        hover:bg-base-200/70
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


            {{-- Capabilities --}}
            <div
                class="
                    mt-8
                    flex flex-wrap
                    items-center
                    justify-center
                    gap-x-5 gap-y-3

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
                        name="lucide.package"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    نصب برنامه‌ها
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.activity"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    پایش منابع سرور
                </span>
            </div>
        </div>


        {{-- Interactive product preview --}}
        <div
            x-data="{ active: 'server' }"
            class="
                relative
                mx-auto
                w-full max-w-xl

                lg:max-w-none
            "
        >
            {{-- Product glow --}}
            <div
                aria-hidden="true"
                class="
                    absolute
                    inset-x-12
                    top-10
                    h-4/5

                    rounded-[3rem]
                    bg-primary/[0.12]
                    blur-3xl
                "
            ></div>


            {{-- Main shell --}}
            <div
                class="
                    relative
                    overflow-hidden

                    rounded-[1.8rem]

                    border border-base-300/80
                    bg-base-100/85

                    shadow-[0_30px_100px_rgba(15,23,42,0.09)]

                    backdrop-blur-2xl
                "
            >
                {{-- Window header --}}
                <div
                    class="
                        flex
                        items-center
                        justify-between

                        border-b border-base-300/70

                        px-4 py-3.5

                        sm:px-5
                    "
                >
                    <div
                        aria-hidden="true"
                        class="flex items-center gap-2"
                    >
                        <span class="size-2 rounded-full bg-error/50"></span>
                        <span class="size-2 rounded-full bg-warning/50"></span>
                        <span class="size-2 rounded-full bg-success/50"></span>
                    </div>

                    <div
                        dir="ltr"
                        class="
                            flex
                            items-center gap-2

                            rounded-lg
                            border border-base-300/70
                            bg-base-200/40

                            px-3 py-1.5

                            font-mono
                            text-[10px]
                            text-base-content/40
                        "
                    >
                        <span
                            class="
                                size-1.5
                                rounded-full
                                bg-success
                            "
                        ></span>

                        {{ $productHost }}
                    </div>

                    <span
                        class="
                            flex size-7
                            items-center justify-center

                            rounded-lg
                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.layers-3"
                            class="!size-3.5 stroke-[1.8]"
                        />
                    </span>
                </div>


                {{-- Product navigation --}}
                <div
                    role="tablist"
                    aria-label="پیش‌نمایش قابلیت‌ها"
                    class="
                        flex items-center gap-1

                        border-b border-base-300/60

                        px-3 py-2.5

                        sm:px-5
                    "
                >
                    <button
                        type="button"
                        role="tab"
                        @click="active = 'server'"
                        :aria-selected="active === 'server'"
                        :class="active === 'server'
                            ? 'bg-base-200 text-base-content'
                            : 'text-base-content/45 hover:text-base-content/70'"
                        class="
                            inline-flex
                            items-center gap-2

                            rounded-lg

                            px-3 py-2

                            text-xs font-medium

                            transition-colors
                            duration-150
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-3.5 stroke-[1.7]"
                        />

                        سرور
                    </button>


                    <button
                        type="button"
                        role="tab"
                        @click="active = 'applications'"
                        :aria-selected="active === 'applications'"
                        :class="active === 'applications'
                            ? 'bg-base-200 text-base-content'
                            : 'text-base-content/45 hover:text-base-content/70'"
                        class="
                            inline-flex
                            items-center gap-2

                            rounded-lg

                            px-3 py-2

                            text-xs font-medium

                            transition-colors
                            duration-150
                        "
                    >
                        <x-icon
                            name="lucide.blocks"
                            class="!size-3.5 stroke-[1.7]"
                        />

                        برنامه‌ها
                    </button>


                    <button
                        type="button"
                        role="tab"
                        @click="active = 'monitoring'"
                        :aria-selected="active === 'monitoring'"
                        :class="active === 'monitoring'
                            ? 'bg-base-200 text-base-content'
                            : 'text-base-content/45 hover:text-base-content/70'"
                        class="
                            inline-flex
                            items-center gap-2

                            rounded-lg

                            px-3 py-2

                            text-xs font-medium

                            transition-colors
                            duration-150
                        "
                    >
                        <x-icon
                            name="lucide.activity"
                            class="!size-3.5 stroke-[1.7]"
                        />

                        پایش
                    </button>
                </div>


                {{-- Stable preview viewport --}}
                <div class="p-4 sm:p-5">
                    <div
                        class="
                            relative
                            h-[390px]
                            overflow-hidden
                        "
                    >
                        {{-- Server --}}
                        <div
                            x-cloak
                            x-show="active === 'server'"
                            x-transition:enter="
                                transition
                                ease-out
                                duration-200
                            "
                            x-transition:enter-start="
                                opacity-0
                                translate-y-1
                            "
                            x-transition:enter-end="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave="
                                transition
                                ease-in
                                duration-150
                            "
                            x-transition:leave-start="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave-end="
                                opacity-0
                                -translate-y-1
                            "
                            class="absolute inset-0"
                        >
                            {{-- Server headline --}}
                            <div
                                class="
                                    flex
                                    items-start
                                    justify-between
                                    gap-4

                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-200/35

                                    p-4
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
                                            flex size-11 shrink-0
                                            items-center justify-center

                                            rounded-xl
                                            bg-primary/10
                                            text-primary
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
                                                text-sm font-semibold
                                                text-base-content
                                            "
                                        >
                                            سرور اصلی
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
                                        shrink-0
                                        items-center gap-1.5

                                        rounded-full
                                        bg-success/10

                                        px-2.5 py-1.5

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

                                    فعال
                                </span>
                            </div>


                            {{-- Stats --}}
                            <div
                                class="
                                    mt-3
                                    grid grid-cols-3
                                    gap-2.5
                                "
                            >
                                <div
                                    class="
                                        rounded-xl
                                        border border-base-300/60
                                        bg-base-100
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
                                            mt-2
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        12%
                                    </div>
                                </div>

                                <div
                                    class="
                                        rounded-xl
                                        border border-base-300/60
                                        bg-base-100
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
                                            mt-2
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        1.2 GB
                                    </div>
                                </div>

                                <div
                                    class="
                                        rounded-xl
                                        border border-base-300/60
                                        bg-base-100
                                        p-3
                                    "
                                >
                                    <div
                                        class="
                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        Uptime
                                    </div>

                                    <div
                                        dir="ltr"
                                        class="
                                            mt-2
                                            text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        18d
                                    </div>
                                </div>
                            </div>


                            {{-- Readiness --}}
                            <div
                                class="
                                    mt-3

                                    rounded-2xl
                                    border border-success/15
                                    bg-success/[0.035]

                                    p-4
                                "
                            >
                                <div
                                    class="
                                        flex
                                        items-center gap-3
                                    "
                                >
                                    <span
                                        class="
                                            flex size-9
                                            items-center justify-center

                                            rounded-xl
                                            bg-success/10
                                            text-success
                                        "
                                    >
                                        <x-icon
                                            name="lucide.circle-check"
                                            class="!size-4 stroke-[1.8]"
                                        />
                                    </span>

                                    <div>
                                        <div
                                            class="
                                                text-xs font-medium
                                                text-base-content/80
                                            "
                                        >
                                            سرور آماده مدیریت است
                                        </div>

                                        <div
                                            class="
                                                mt-1
                                                text-[10px]
                                                text-base-content/40
                                            "
                                        >
                                            اتصال SSH و دسترسی‌های موردنیاز
                                            با موفقیت تأیید شده‌اند
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        {{-- Applications --}}
                        <div
                            x-cloak
                            x-show="active === 'applications'"
                            x-transition:enter="
                                transition
                                ease-out
                                duration-200
                            "
                            x-transition:enter-start="
                                opacity-0
                                translate-y-1
                            "
                            x-transition:enter-end="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave="
                                transition
                                ease-in
                                duration-150
                            "
                            x-transition:leave-start="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave-end="
                                opacity-0
                                -translate-y-1
                            "
                            class="
                                absolute inset-0
                                space-y-2.5
                            "
                        >
                            {{-- Marzban --}}
                            <div
                                class="
                                    flex
                                    items-center justify-between
                                    gap-3

                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100

                                    p-3.5
                                "
                            >
                                <div
                                    class="
                                        flex
                                        min-w-0
                                        items-center gap-3
                                    "
                                >
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
                                            name="lucide.shield-check"
                                            class="!size-[18px] stroke-[1.7]"
                                        />
                                    </span>

                                    <div class="min-w-0">
                                        <div
                                            dir="ltr"
                                            class="
                                                text-sm font-medium
                                                text-base-content
                                            "
                                        >
                                            Marzban
                                        </div>

                                        <div
                                            class="
                                                mt-1
                                                text-[10px]
                                                text-base-content/40
                                            "
                                        >
                                            مدیریت کاربران و سرویس‌های مبتنی بر Xray
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="
                                        shrink-0

                                        rounded-full
                                        bg-success/10

                                        px-2.5 py-1

                                        text-[10px] font-medium
                                        text-success
                                    "
                                >
                                    در حال اجرا
                                </span>
                            </div>


                            {{-- n8n --}}
                            <div
                                class="
                                    flex
                                    items-center justify-between
                                    gap-3

                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100

                                    p-3.5
                                "
                            >
                                <div
                                    class="
                                        flex
                                        min-w-0
                                        items-center gap-3
                                    "
                                >
                                    <span
                                        class="
                                            flex size-10 shrink-0
                                            items-center justify-center

                                            rounded-xl
                                            bg-warning/10
                                            text-warning
                                        "
                                    >
                                        <x-icon
                                            name="lucide.workflow"
                                            class="!size-[18px] stroke-[1.7]"
                                        />
                                    </span>

                                    <div class="min-w-0">
                                        <div
                                            dir="ltr"
                                            class="
                                                text-sm font-medium
                                                text-base-content
                                            "
                                        >
                                            n8n
                                        </div>

                                        <div
                                            class="
                                                mt-1
                                                text-[10px]
                                                text-base-content/40
                                            "
                                        >
                                            اتوماسیون گردش‌کار و اتصال سرویس‌ها
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="
                                        shrink-0

                                        rounded-full
                                        bg-success/10

                                        px-2.5 py-1

                                        text-[10px] font-medium
                                        text-success
                                    "
                                >
                                    در حال اجرا
                                </span>
                            </div>


                            {{-- AmneziaWG --}}
                            <div
                                class="
                                    flex
                                    items-center justify-between
                                    gap-3

                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100

                                    p-3.5
                                "
                            >
                                <div
                                    class="
                                        flex
                                        min-w-0
                                        items-center gap-3
                                    "
                                >
                                    <span
                                        class="
                                            flex size-10 shrink-0
                                            items-center justify-center

                                            rounded-xl
                                            bg-info/10
                                            text-info
                                        "
                                    >
                                        <x-icon
                                            name="lucide.network"
                                            class="!size-[18px] stroke-[1.7]"
                                        />
                                    </span>

                                    <div class="min-w-0">
                                        <div
                                            dir="ltr"
                                            class="
                                                text-sm font-medium
                                                text-base-content
                                            "
                                        >
                                            AmneziaWG
                                        </div>

                                        <div
                                            class="
                                                mt-1
                                                text-[10px]
                                                text-base-content/40
                                            "
                                        >
                                            VPN شخصی و مدیریت دستگاه‌ها
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="
                                        shrink-0

                                        rounded-full
                                        bg-primary/10

                                        px-2.5 py-1

                                        text-[10px] font-medium
                                        text-primary
                                    "
                                >
                                    نصب نشده
                                </span>
                            </div>
                        </div>


                        {{-- Monitoring --}}
                        <div
                            x-cloak
                            x-show="active === 'monitoring'"
                            x-transition:enter="
                                transition
                                ease-out
                                duration-200
                            "
                            x-transition:enter-start="
                                opacity-0
                                translate-y-1
                            "
                            x-transition:enter-end="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave="
                                transition
                                ease-in
                                duration-150
                            "
                            x-transition:leave-start="
                                opacity-100
                                translate-y-0
                            "
                            x-transition:leave-end="
                                opacity-0
                                -translate-y-1
                            "
                            class="
                                absolute inset-0
                                space-y-3
                            "
                        >
                            {{-- CPU --}}
                            <div
                                class="
                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100
                                    p-4
                                "
                            >
                                <div
                                    class="
                                        flex
                                        items-center justify-between
                                    "
                                >
                                    <span
                                        class="
                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        پردازنده
                                    </span>

                                    <span
                                        dir="ltr"
                                        class="
                                            text-xs font-semibold
                                            text-base-content
                                        "
                                    >
                                        12%
                                    </span>
                                </div>

                                <progress
                                    class="
                                        progress progress-primary
                                        mt-3 h-1.5
                                        w-full
                                    "
                                    value="12"
                                    max="100"
                                ></progress>
                            </div>


                            {{-- RAM --}}
                            <div
                                class="
                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100
                                    p-4
                                "
                            >
                                <div
                                    class="
                                        flex
                                        items-center justify-between
                                    "
                                >
                                    <span
                                        class="
                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        حافظه
                                    </span>

                                    <span
                                        dir="ltr"
                                        class="
                                            text-xs font-semibold
                                            text-base-content
                                        "
                                    >
                                        38%
                                    </span>
                                </div>

                                <progress
                                    class="
                                        progress progress-primary
                                        mt-3 h-1.5
                                        w-full
                                    "
                                    value="38"
                                    max="100"
                                ></progress>
                            </div>


                            {{-- Disk --}}
                            <div
                                class="
                                    rounded-2xl
                                    border border-base-300/70
                                    bg-base-100
                                    p-4
                                "
                            >
                                <div
                                    class="
                                        flex
                                        items-center justify-between
                                    "
                                >
                                    <span
                                        class="
                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        فضای دیسک
                                    </span>

                                    <span
                                        dir="ltr"
                                        class="
                                            text-xs font-semibold
                                            text-base-content
                                        "
                                    >
                                        24%
                                    </span>
                                </div>

                                <progress
                                    class="
                                        progress progress-primary
                                        mt-3 h-1.5
                                        w-full
                                    "
                                    value="24"
                                    max="100"
                                ></progress>
                            </div>


                            {{-- Services --}}
                            <div
                                class="
                                    flex
                                    items-center justify-between
                                    gap-4

                                    rounded-2xl
                                    border border-success/15
                                    bg-success/[0.035]

                                    p-4
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
                                            size-2 shrink-0
                                            rounded-full
                                            bg-success
                                        "
                                    ></span>

                                    <span
                                        class="
                                            text-xs font-medium
                                            text-base-content/70
                                        "
                                    >
                                        سرویس‌های اصلی فعال هستند
                                    </span>
                                </div>

                                <span
                                    class="
                                        shrink-0
                                        text-[10px]
                                        text-base-content/35
                                    "
                                >
                                    هم‌اکنون
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            {{-- Floating status --}}
            <div
                class="
                    absolute
                    -bottom-5 start-7

                    hidden
                    items-center gap-2.5

                    rounded-xl

                    border border-base-300/80
                    bg-base-100/90

                    px-3 py-2.5

                    shadow-xl
                    shadow-base-content/[0.045]

                    backdrop-blur-xl

                    sm:flex
                "
            >
                <span
                    class="
                        flex size-8
                        items-center justify-center

                        rounded-lg
                        bg-success/10
                        text-success
                    "
                >
                    <x-icon
                        name="lucide.shield-check"
                        class="!size-4 stroke-[1.8]"
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
