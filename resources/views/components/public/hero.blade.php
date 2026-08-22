@php
    $productHost = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');

    $resources = [
        ['CPU', '12%', 12],
        ['RAM', '38%', 38],
        ['Disk', '24%', 24],
    ];
@endphp

<section
    class="
        relative isolate overflow-hidden
        border-b border-base-300/60
    "
>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 -z-20 overflow-hidden"
    >
        <div
            class="
                absolute -top-64 start-1/2
                size-[54rem] -translate-x-1/2
                rounded-full bg-primary/[0.08] blur-3xl
            "
        ></div>

        <div
            class="
                absolute bottom-[-22rem] end-[-16rem]
                size-[40rem] rounded-full
                bg-accent/[0.045] blur-3xl
            "
        ></div>

        <div
            class="absolute inset-0 opacity-[0.022] dark:opacity-[0.035]"
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
            },
        }"
        class="
            pointer-events-none absolute inset-x-0 top-14 -z-10
            hidden select-none overflow-hidden lg:block
        "
    >
        <div
            dir="ltr"
            class="
                mx-auto w-full max-w-7xl px-8 text-center
                text-[6.25rem] font-semibold leading-none
                tracking-[-0.065em] text-base-content/[0.045]
                xl:text-[7.25rem] dark:text-base-content/[0.055]
            "
        >
            <span x-text="output"></span>
        </div>
    </div>

    <div
        class="
            mx-auto grid min-h-[calc(100svh-4rem)]
            w-full max-w-7xl items-center gap-14
            px-4 py-16
            sm:px-6 sm:py-20
            lg:grid-cols-[0.88fr_1.12fr]
            lg:gap-20 lg:px-8 lg:pb-24 lg:pt-28
        "
    >
        <div class="mx-auto max-w-2xl text-center lg:mx-0 lg:text-start">
            <div
                class="
                    inline-flex items-center gap-2 rounded-full
                    border border-primary/15 bg-primary/[0.055]
                    px-3 py-1.5 text-xs font-medium text-primary
                "
            >
                <span class="relative flex size-2">
                    <span
                        class="
                            absolute inline-flex size-full animate-ping
                            rounded-full bg-success/60 opacity-60
                        "
                    ></span>
                    <span class="relative inline-flex size-2 rounded-full bg-success"></span>
                </span>

                <span dir="ltr">Coreflare</span>
                <span class="text-base-content/25">|</span>
                <span>کورفلر</span>
            </div>

            <h1
                class="
                    mt-6 text-4xl font-semibold leading-[1.35]
                    tracking-tight text-base-content
                    sm:text-5xl sm:leading-[1.3]
                    lg:text-[3.55rem]
                "
            >
                از سرور تا سرویس،
                <span class="text-primary">در یک پنل</span>
            </h1>

            <p
                class="
                    mx-auto mt-6 max-w-xl
                    text-base leading-8 text-base-content/60
                    sm:text-lg sm:leading-9
                    lg:mx-0
                "
            >
                سرور خود را متصل کنید یا یک VPS تهیه کنید. سرویس موردنیازتان را
                راه‌اندازی کنید و وضعیت زیرساخت را از یک محیط واحد مدیریت کنید.
            </p>

            <div
                class="
                    mt-8 flex flex-col items-stretch justify-center gap-3
                    sm:flex-row sm:items-center
                    lg:justify-start
                "
            >
                <a
                    href="{{ route('login') }}"
                    class="
                        btn btn-primary btn-lg rounded-xl px-6
                        font-medium shadow-lg shadow-primary/10
                    "
                >
                    <span>شروع استفاده</span>
                    <x-icon
                        name="lucide.arrow-left"
                        class="!size-4 stroke-[1.8]"
                    />
                </a>

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
                        btn btn-ghost btn-lg rounded-xl px-5
                        font-normal text-base-content/60
                        hover:bg-base-200/70 hover:text-base-content
                    "
                >
                    <span>نحوه کار</span>
                    <x-icon
                        name="lucide.chevron-down"
                        class="!size-4 stroke-[1.7]"
                    />
                </a>
            </div>

            <div
                class="
                    mt-8 flex flex-wrap items-center justify-center
                    gap-x-5 gap-y-3 text-xs text-base-content/45
                    lg:justify-start
                "
            >
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="lucide.server" class="!size-3.5 stroke-[1.6]" />
                    اتصال یا تهیه سرور
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="lucide.blocks" class="!size-3.5 stroke-[1.6]" />
                    راه‌اندازی سرویس
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="lucide.activity" class="!size-3.5 stroke-[1.6]" />
                    وضعیت و منابع
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="lucide.globe-lock" class="!size-3.5 stroke-[1.6]" />
                    دامنه و HTTPS
                </span>
            </div>
        </div>

        {{-- Product preview --}}
        <div class="relative hidden w-full lg:block">
            <div
                aria-hidden="true"
                class="
                    absolute inset-x-16 top-10 h-4/5
                    rounded-[3rem] bg-primary/[0.10] blur-3xl
                "
            ></div>

            <div
                class="
                    relative overflow-hidden rounded-[1.75rem]
                    border border-base-300/80 bg-base-100/90
                    shadow-[0_30px_90px_rgba(15,23,42,0.08)]
                    backdrop-blur-xl
                "
            >
                <div
                    class="
                        flex items-center justify-between
                        border-b border-base-300/60 px-5 py-3.5
                    "
                >
                    <div aria-hidden="true" class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-error/45"></span>
                        <span class="size-2 rounded-full bg-warning/45"></span>
                        <span class="size-2 rounded-full bg-success/45"></span>
                    </div>

                    <div
                        dir="ltr"
                        class="
                            flex items-center gap-2 rounded-lg
                            border border-base-300/60 bg-base-200/35
                            px-3 py-1.5 text-[10px] text-base-content/35
                        "
                    >
                        <span class="size-1.5 rounded-full bg-success"></span>
                        {{ $productHost }}
                    </div>

                    <span
                        class="
                            flex size-7 items-center justify-center
                            rounded-lg bg-primary/10 text-primary
                        "
                    >
                        <x-icon name="lucide.layers-3" class="!size-3.5 stroke-[1.8]" />
                    </span>
                </div>

                <div class="p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-base-content">
                                نمای کلی زیرساخت
                            </div>
                            <div class="mt-1 text-[11px] text-base-content/40">
                                وضعیت سرور و سرویس‌های شما
                            </div>
                        </div>

                        <div
                            class="
                                inline-flex items-center gap-1.5 rounded-full
                                bg-success/[0.08] px-2.5 py-1.5
                                text-[10px] font-medium text-success
                            "
                        >
                            <span class="size-1.5 rounded-full bg-success"></span>
                            آماده
                        </div>
                    </div>

                    <div
                        class="
                            mt-5 flex items-center justify-between gap-4
                            rounded-2xl border border-base-300/70
                            bg-base-200/30 p-4
                        "
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="
                                    flex size-10 shrink-0 items-center justify-center
                                    rounded-xl bg-primary/10 text-primary
                                "
                            >
                                <x-icon name="lucide.server" class="!size-[18px] stroke-[1.7]" />
                            </span>

                            <div class="min-w-0">
                                <div class="text-sm font-medium text-base-content">سرور اصلی</div>
                                <div dir="ltr" class="mt-1 text-[10px] text-base-content/40">
                                    Ubuntu 24.04 · 185.10.20.41
                                </div>
                            </div>
                        </div>

                        <span
                            class="
                                inline-flex shrink-0 items-center gap-1.5
                                text-[10px] font-medium text-success
                            "
                        >
                            <span class="size-1.5 rounded-full bg-success"></span>
                            فعال
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2.5">
                        @foreach($resources as [$label, $value, $progress])
                            <div
                                class="
                                    rounded-xl border border-base-300/60
                                    bg-base-100 p-3
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span dir="ltr" class="text-[10px] text-base-content/40">
                                        {{ $label }}
                                    </span>
                                    <span
                                        dir="ltr"
                                        class="numeric-value text-xs font-semibold text-base-content/75"
                                    >
                                        {{ $value }}
                                    </span>
                                </div>

                                <progress
                                    class="progress progress-primary mt-3 h-1 w-full"
                                    value="{{ $progress }}"
                                    max="100"
                                ></progress>
                            </div>
                        @endforeach
                    </div>

                    <div
                        class="
                            mt-3 flex items-center justify-between gap-4
                            rounded-2xl border border-base-300/60
                            bg-base-100 px-4 py-3.5
                        "
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="
                                    flex size-9 items-center justify-center
                                    rounded-xl bg-primary/[0.07] text-primary
                                "
                            >
                                <x-icon name="lucide.blocks" class="!size-4 stroke-[1.7]" />
                            </span>

                            <div>
                                <div class="text-xs font-medium text-base-content/75">سرویس‌ها</div>
                                <div class="mt-1 text-[10px] text-base-content/40">
                                    Marzban، n8n و WordPress در حال اجرا هستند
                                </div>
                            </div>
                        </div>

                        <div class="flex -space-x-1.5 space-x-reverse">
                            <span
                                class="
                                    flex size-7 items-center justify-center rounded-lg
                                    border-2 border-base-100 bg-primary/10 text-primary
                                "
                            >
                                <x-icon name="lucide.shield-check" class="!size-3 stroke-[1.8]" />
                            </span>
                            <span
                                class="
                                    flex size-7 items-center justify-center rounded-lg
                                    border-2 border-base-100 bg-warning/10 text-warning
                                "
                            >
                                <x-icon name="lucide.workflow" class="!size-3 stroke-[1.8]" />
                            </span>
                        </div>
                    </div>

                    <div
                        class="
                            mt-3 flex items-center gap-3 rounded-2xl
                            border border-success/15 bg-success/[0.035]
                            px-4 py-3.5
                        "
                    >
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-success/10 text-success
                            "
                        >
                            <x-icon name="lucide.circle-check" class="!size-4 stroke-[1.8]" />
                        </span>

                        <div>
                            <div class="text-xs font-medium text-base-content/75">
                                زیرساخت آماده است
                            </div>
                            <div class="mt-1 text-[10px] text-base-content/40">
                                سرور و سرویس‌های اصلی در وضعیت مطلوب هستند
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
