@php
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
            absolute inset-0 -z-20
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


    {{-- Ambient Coreflare wordmark --}}
    <div
        aria-hidden="true"
        x-data="{
        text: 'Coreflare',
        output: '',
        index: 0,
        timeout: null,

        init() {
            this.start();
        },

        destroy() {
            if (this.timeout) {
                clearTimeout(this.timeout);
            }
        },

        start() {
            if (
                window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches
            ) {
                this.output = this.text;

                return;
            }

            this.output = '';
            this.index = 0;

            this.write();
        },

        write() {
            if (this.index >= this.text.length) {
                this.timeout = setTimeout(() => {
                    this.start();
                }, 24000);

                return;
            }

            this.output += this.text.charAt(this.index);

            this.index++;

            this.timeout = setTimeout(() => {
                this.write();
            }, 88);
        }
    }"
        class="
        pointer-events-none
        absolute
        inset-x-0
        top-14
        -z-10

        hidden
        select-none
        overflow-hidden

        lg:block
    "
    >
        <div
            dir="ltr"
            class="
            mx-auto
            w-full max-w-7xl

            px-8
            text-center

            text-[6.25rem]
            font-semibold
            leading-none
            tracking-[-0.065em]

            text-base-content/[0.045]

            xl:text-[7.25rem]

            dark:text-base-content/[0.055]
        "
        >
            <span x-text="output"></span>
        </div>
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
            lg:px-8
            lg:pb-24
            lg:pt-28
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
            {{-- Brand --}}
            <div
                class="
                    inline-flex
                    items-center gap-2

                    rounded-full
                    border border-primary/15
                    bg-primary/[0.055]

                    px-3 py-1.5

                    text-xs
                    font-medium
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

                <span dir="ltr">
                    Coreflare
                </span>

                <span class="text-base-content/25">
                    |
                </span>

                <span>
                    کورفلر
                </span>
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
                زیرساخت،

                <span class="text-primary">
                    یکپارچه و هوشمند
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
                کورفلر زیرساخت را از مجموعه‌ای از ابزارها و فرایندهای پراکنده،
                به یک تجربه یکپارچه برای راه‌اندازی، اجرا و مدیریت سرویس‌ها
                تبدیل می‌کند.
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
                    label="شروع"
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
                        چطور کار می‌کند؟
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

                    مدیریت سرورها
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.blocks"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    راه‌اندازی سرویس‌ها
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.activity"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    پایش زیرساخت
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon
                        name="lucide.layers-3"
                        class="!size-3.5 stroke-[1.6]"
                    />

                    مدیریت یکپارچه
                </span>
            </div>
        </div>

        {{-- Product preview --}}
        <div
            class="
        relative
        hidden
        w-full
        lg:block
    "
        >
            {{-- Ambient glow --}}
            <div
                aria-hidden="true"
                class="
            absolute
            inset-x-16
            top-10
            h-4/5

            rounded-[3rem]
            bg-primary/[0.10]
            blur-3xl
        "
            ></div>


            {{-- Preview shell --}}
            <div
                class="
            relative
            overflow-hidden

            rounded-[1.75rem]

            border border-base-300/80
            bg-base-100/90

            shadow-[0_30px_90px_rgba(15,23,42,0.08)]

            backdrop-blur-xl
        "
            >
                {{-- Window header --}}
                <div
                    class="
                flex
                items-center
                justify-between

                border-b border-base-300/60

                px-5 py-3.5
            "
                >
                    {{-- Window controls --}}
                    <div
                        aria-hidden="true"
                        class="
                    flex
                    items-center
                    gap-2
                "
                    >
                <span
                    class="
                        size-2
                        rounded-full
                        bg-error/45
                    "
                ></span>

                        <span
                            class="
                        size-2
                        rounded-full
                        bg-warning/45
                    "
                        ></span>

                        <span
                            class="
                        size-2
                        rounded-full
                        bg-success/45
                    "
                        ></span>
                    </div>


                    {{-- Host --}}
                    <div
                        dir="ltr"
                        class="
                    flex
                    items-center
                    gap-2

                    rounded-lg

                    border border-base-300/60
                    bg-base-200/35

                    px-3 py-1.5

                    font-mono
                    text-[10px]
                    text-base-content/35
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


                    {{-- Brand mark --}}
                    <span
                        class="
                    flex
                    size-7
                    items-center
                    justify-center

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


                {{-- Preview body --}}
                <div class="p-5">
                    {{-- Overview header --}}
                    <div
                        class="
                    flex
                    items-center
                    justify-between
                    gap-4
                "
                    >
                        <div>
                            <div
                                class="
                            text-sm
                            font-semibold
                            text-base-content
                        "
                            >
                                نمای کلی زیرساخت
                            </div>

                            <div
                                class="
                            mt-1
                            text-[11px]
                            text-base-content/40
                        "
                            >
                                وضعیت سرور و سرویس‌های فعال
                            </div>
                        </div>


                        <div
                            class="
                        inline-flex
                        items-center
                        gap-1.5

                        rounded-full
                        bg-success/[0.08]

                        px-2.5 py-1.5

                        text-[10px]
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

                            پایدار
                        </div>
                    </div>


                    {{-- Server --}}
                    <div
                        class="
                    mt-5

                    flex
                    items-center
                    justify-between
                    gap-4

                    rounded-2xl

                    border border-base-300/70
                    bg-base-200/30

                    p-4
                "
                    >
                        <div
                            class="
                        flex
                        min-w-0
                        items-center
                        gap-3
                    "
                        >
                    <span
                        class="
                            flex
                            size-10
                            shrink-0
                            items-center
                            justify-center

                            rounded-xl

                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-[18px] stroke-[1.7]"
                        />
                    </span>


                            <div class="min-w-0">
                                <div
                                    class="
                                text-sm
                                font-medium
                                text-base-content
                            "
                                >
                                    سرور اصلی
                                </div>

                                <div
                                    dir="ltr"
                                    class="
                                mt-1
                                text-[10px]
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
                        items-center
                        gap-1.5

                        text-[10px]
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

                    فعال
                </span>
                    </div>


                    {{-- Resources --}}
                    <div
                        class="
                    mt-3
                    grid
                    grid-cols-3
                    gap-2.5
                "
                    >
                        {{-- CPU --}}
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
                            flex
                            items-center
                            justify-between
                        "
                            >
                        <span
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            CPU
                        </span>

                                <span
                                    dir="ltr"
                                    class="
                                text-xs
                                font-semibold
                                text-base-content/75
                            "
                                >
                            12%
                        </span>
                            </div>

                            <progress
                                class="
                            progress progress-primary

                            mt-3
                            h-1
                            w-full
                        "
                                value="12"
                                max="100"
                            ></progress>
                        </div>


                        {{-- RAM --}}
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
                            flex
                            items-center
                            justify-between
                        "
                            >
                        <span
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            RAM
                        </span>

                                <span
                                    dir="ltr"
                                    class="
                                text-xs
                                font-semibold
                                text-base-content/75
                            "
                                >
                            38%
                        </span>
                            </div>

                            <progress
                                class="
                            progress progress-primary

                            mt-3
                            h-1
                            w-full
                        "
                                value="38"
                                max="100"
                            ></progress>
                        </div>


                        {{-- Disk --}}
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
                            flex
                            items-center
                            justify-between
                        "
                            >
                        <span
                            class="
                                text-[10px]
                                text-base-content/40
                            "
                        >
                            Disk
                        </span>

                                <span
                                    dir="ltr"
                                    class="
                                text-xs
                                font-semibold
                                text-base-content/75
                            "
                                >
                            24%
                        </span>
                            </div>

                            <progress
                                class="
                            progress progress-primary

                            mt-3
                            h-1
                            w-full
                        "
                                value="24"
                                max="100"
                            ></progress>
                        </div>
                    </div>


                    {{-- Services --}}
                    <div
                        class="
                    mt-3

                    flex
                    items-center
                    justify-between
                    gap-4

                    rounded-2xl

                    border border-base-300/60
                    bg-base-100

                    px-4 py-3.5
                "
                    >
                        <div
                            class="
                        flex
                        items-center
                        gap-3
                    "
                        >
                    <span
                        class="
                            flex
                            size-9
                            items-center
                            justify-center

                            rounded-xl

                            bg-primary/[0.07]
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.blocks"
                            class="!size-4 stroke-[1.7]"
                        />
                    </span>

                            <div>
                                <div
                                    class="
                                text-xs
                                font-medium
                                text-base-content/75
                            "
                                >
                                    سرویس‌ها
                                </div>

                                <div
                                    class="
                                mt-1
                                text-[10px]
                                text-base-content/40
                            "
                                >
                                    Marzban و n8n در حال اجرا هستند
                                </div>
                            </div>
                        </div>


                        <div
                            class="
                        flex
                        -space-x-1.5
                        space-x-reverse
                    "
                        >
                    <span
                        class="
                            flex
                            size-7
                            items-center
                            justify-center

                            rounded-lg

                            border-2 border-base-100
                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.shield-check"
                            class="!size-3 stroke-[1.8]"
                        />
                    </span>

                            <span
                                class="
                            flex
                            size-7
                            items-center
                            justify-center

                            rounded-lg

                            border-2 border-base-100
                            bg-warning/10
                            text-warning
                        "
                            >
                        <x-icon
                            name="lucide.workflow"
                            class="!size-3 stroke-[1.8]"
                        />
                    </span>
                        </div>
                    </div>


                    {{-- System status --}}
                    <div
                        class="
                    mt-3

                    flex
                    items-center
                    gap-3

                    rounded-2xl

                    border border-success/15
                    bg-success/[0.035]

                    px-4 py-3.5
                "
                    >
                <span
                    class="
                        flex
                        size-8
                        shrink-0
                        items-center
                        justify-center

                        rounded-lg

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
                            text-xs
                            font-medium
                            text-base-content/75
                        "
                            >
                                زیرساخت آماده است
                            </div>

                            <div
                                class="
                            mt-1
                            text-[10px]
                            text-base-content/40
                        "
                            >
                                سرور و سرویس‌های اصلی بدون مشکل در حال اجرا هستند
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



    </div>
</section>
