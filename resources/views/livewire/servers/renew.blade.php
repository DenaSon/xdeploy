<x-servers.workspace
    :server="$server"
    wire:key="server-renewal-{{ $server->getKey() }}"
>
    <div
        wire:init="loadQuote"
        class="pb-24 xl:pb-0"
    >
        {{-- Page heading --}}
        <header
            class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-primary/15 bg-primary/5 text-primary"
                    >
                        <x-icon name="lucide.calendar-plus" class="!size-5 stroke-[1.8]" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold tracking-tight text-base-content sm:text-2xl">
                            تمدید سرویس
                        </h1>
                        <p class="mt-1 text-xs leading-6 text-base-content/45 sm:text-sm">
                            مدت استفاده از همین VPS را بدون تغییر منابع یا سیستم‌عامل افزایش دهید.
                        </p>
                    </div>
                </div>
            </div>
            <x-button
                label="بازگشت به سرور"
                icon="lucide.arrow-right"
                :link="route('panel.servers.dashboard', $server)"
                wire:navigate
                class="btn-ghost btn-sm self-start rounded-xl text-base-content/55 sm:self-auto"
            />
        </header>

        @if($paymentResult === 'success')
            <div role="status" class="mb-4 flex items-start gap-3 rounded-2xl border border-success/20 bg-success/[0.06] px-4 py-3.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                    <x-icon name="lucide.circle-check" class="!size-4.5" />
                </div>
                <div>
                    <div class="text-sm font-semibold text-success">تمدید با موفقیت انجام شد</div>
                    <p class="mt-1 text-xs leading-6 text-base-content/50">
                        تاریخ پایان سرویس به‌روزرسانی شده و VPS بدون وقفه فعال می‌ماند.
                    </p>
                </div>
            </div>
        @elseif($paymentResult === 'cancelled')
            <div role="status" class="mb-4 flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning/[0.05] px-4 py-3.5">
                <x-icon name="lucide.circle-alert" class="mt-0.5 !size-4.5 shrink-0 text-warning" />
                <p class="text-xs leading-6 text-base-content/55 sm:text-sm">
                    پرداخت تکمیل نشد. تا زمانی که سرویس منقضی نشده است می‌توانید دوباره پرداخت را آغاز کنید.
                </p>
            </div>
        @endif

        @if(! $canRenew)
            <section class="rounded-2xl border border-warning/20 bg-warning/[0.05] px-5 py-7">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                        <x-icon name="lucide.clock-alert" class="!size-5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-base-content sm:text-base">
                            این سرویس در حال حاضر قابل تمدید نیست
                        </h2>
                        <p class="mt-1 max-w-2xl text-xs leading-6 text-base-content/50 sm:text-sm">
                            تمدید فقط برای VPS ابری فعال و پیش از شروع فرایند پایان سرویس امکان‌پذیر است.
                        </p>
                    </div>
                </div>
            </section>
        @else
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
                <main class="min-w-0 space-y-4">
                    <section
                        @class([
                            'overflow-hidden rounded-2xl border bg-base-100',
                            'border-warning/25' => $isExpiringSoon,
                            'border-base-300' => ! $isExpiringSoon,
                        ])
                    >
                        <div
                            @class([
                                'flex items-center justify-between gap-3 border-b px-4 py-3.5 sm:px-5',
                                'border-warning/15 bg-warning/[0.045]' => $isExpiringSoon,
                                'border-base-300 bg-base-200/25' => ! $isExpiringSoon,
                            ])
                        >
                            <div>
                                <h2 class="text-sm font-semibold text-base-content">سرویس فعلی</h2>
                                <p class="mt-0.5 text-[11px] text-base-content/40">
                                    مشخصات این VPS در تمدید تغییر نمی‌کند
                                </p>
                            </div>



                            @if($isExpiringSoon)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-warning/20 bg-warning/10 px-2.5 py-1 text-[10px] font-medium text-warning">
                                    <x-icon name="lucide.clock-alert" class="!size-3.5" />
                                    نزدیک انقضا
                                </span>
                            @endif
                        </div>

                        <div class="grid gap-px bg-base-300 sm:grid-cols-3">
                            <div class="bg-base-100 px-4 py-3.5">
                                <div class="text-[10px] text-base-content/35">موقعیت</div>
                                <div class="mt-1 truncate text-xs font-medium text-base-content">{{ $regionLabel }}</div>
                            </div>
                            <div class="bg-base-100 px-4 py-3.5">
                                <div class="text-[10px] text-base-content/35">سیستم‌عامل</div>
                                <div dir="ltr" class="technical-value mt-1 truncate text-xs font-medium text-base-content">
                                    @if($sourceOrder)
                                        {{ $sourceOrder->image_distribution }} {{ $sourceOrder->image_version }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="bg-base-100 px-4 py-3.5">
                                <div class="text-[10px] text-base-content/35">دیسک</div>
                                <div dir="ltr" class="technical-value mt-1 text-xs font-medium text-base-content">
                                    {{ $sourceOrder?->selected_disk_gib ?? '—' }} GB
                                </div>
                            </div>
                        </div>

                        @if($currentExpiresAt)
                            <div
                                wire:ignore
                                x-data="{
            endAt: {{ $currentExpiresAt->getTimestamp() * 1000 }},
            now: Date.now(),
            timer: null,

            init() {
                this.timer = setInterval(() => {
                    this.now = Date.now()
                }, 1000)
            },

            destroy() {
                clearInterval(this.timer)
            },

            get totalSeconds() {
                return Math.max(
                    0,
                    Math.floor((this.endAt - this.now) / 1000)
                )
            },

            get days() {
                return Math.floor(this.totalSeconds / 86400)
            },

            get hours() {
                return Math.floor((this.totalSeconds % 86400) / 3600)
            },

            get minutes() {
                return Math.floor((this.totalSeconds % 3600) / 60)
            },

            get seconds() {
                return this.totalSeconds % 60
            }
        }"
                                class="flex flex-col gap-3 border-t border-base-300 px-4 py-3
               sm:flex-row sm:items-center sm:justify-between sm:px-5"
                            >
                                {{-- Expiration date --}}
                                <div class="flex items-center gap-2.5">
            <span class="text-xs text-base-content/40">
                تاریخ پایان
            </span>

                                    <span
                                        dir="ltr"
                                        class="technical-value text-xs font-medium text-base-content"
                                    >
                {{ \App\Support\Date\JalaliDateFormatter::dateTime(
                    $currentExpiresAt,
                    ' - '
                ) }}
            </span>

                                    @if($isExpiringSoon)
                                        <span
                                            class="rounded-full bg-warning/10 px-2 py-0.5
                           text-[9px] font-medium text-warning"
                                        >
                    نزدیک انقضا
                </span>
                                    @endif
                                </div>

                                {{-- Countdown --}}
                                <div
                                    dir="ltr"
                                    class="grid auto-cols-max grid-flow-col gap-4 text-center"
                                >
                                    <div class="flex min-w-9 flex-col">
                <span class="countdown font-mono text-xl font-semibold">
                    <span
                        x-bind:style="'--value:' + days"
                        x-bind:aria-label="days"
                        x-text="days"
                    ></span>
                </span>

                                        <span class="text-[9px] text-base-content/35">
                    روز
                </span>
                                    </div>

                                    <div class="flex min-w-9 flex-col">
                <span class="countdown font-mono text-xl font-semibold">
                    <span
                        x-bind:style="'--value:' + hours + '; --digits: 2;'"
                        x-bind:aria-label="hours"
                        x-text="hours"
                    ></span>
                </span>

                                        <span class="text-[9px] text-base-content/35">
                    ساعت
                </span>
                                    </div>

                                    <div class="flex min-w-9 flex-col">
                <span class="countdown font-mono text-xl font-semibold">
                    <span
                        x-bind:style="'--value:' + minutes + '; --digits: 2;'"
                        x-bind:aria-label="minutes"
                        x-text="minutes"
                    ></span>
                </span>

                                        <span class="text-[9px] text-base-content/35">
                    دقیقه
                </span>
                                    </div>

                                    <div class="flex min-w-9 flex-col">
                <span class="countdown font-mono text-xl font-semibold">
                    <span
                        x-bind:style="'--value:' + seconds + '; --digits: 2;'"
                        x-bind:aria-label="seconds"
                        x-text="seconds"
                    ></span>
                </span>

                                        <span class="text-[9px] text-base-content/35">
                    ثانیه
                </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </section>

                    <section class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold text-base-content sm:text-base">مدت تمدید</h2>
                                <p class="mt-0.5 text-[11px] text-base-content/40">دوره موردنظر را انتخاب کنید</p>
                            </div>
                            <span wire:loading wire:target="loadQuote,selectPeriod" class="loading loading-spinner loading-sm text-primary"></span>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            @foreach($periods as $periodOption)
                                <button
                                    type="button"
                                    wire:key="renew-period-{{ $periodOption['id'] }}"
                                    wire:click="selectPeriod('{{ $periodOption['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="selectPeriod"
                                    @class([
                                        'relative flex min-h-24 cursor-pointer flex-col items-start justify-between rounded-xl border p-3.5 text-right transition-all duration-150',
                                        'border-primary bg-primary/[0.055] ring-2 ring-primary/10' => $period === $periodOption['id'],
                                        'border-base-300 bg-base-100 hover:border-primary/25 hover:bg-base-200/25' => $period !== $periodOption['id'],
                                    ])
                                >
                                    <div class="flex w-full items-start justify-between gap-2">
                                        <div>
                                            <div
                                                @class([
                                                    'text-sm font-semibold',
                                                    'text-primary' => $period === $periodOption['id'],
                                                    'text-base-content' => $period !== $periodOption['id'],
                                                ])
                                            >{{ $periodOption['label'] }}</div>
                                            <div class="mt-1 text-[11px] text-base-content/40">{{ $periodOption['hint'] }}</div>
                                        </div>
                                        @if($period === $periodOption['id'])
                                            <div class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-content">
                                                <x-icon name="lucide.check" class="!size-3" />
                                            </div>
                                        @endif
                                    </div>
                                    @if($periodOption['recommended'])
                                        <span class="mt-3 inline-flex rounded-full bg-primary/8 px-2 py-0.5 text-[9px] font-medium text-primary">
                                            پیشنهاد xDeploy
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-start gap-2 rounded-xl border border-info/15 bg-info/[0.04] px-3 py-2.5">
                            <x-icon name="lucide.info" class="mt-0.5 !size-3.5 shrink-0 text-info" />
                            <p class="text-[11px] leading-5 text-base-content/45">
                                زمان باقی‌مانده فعلی از بین نمی‌رود؛ مدت خریداری‌شده به پایان فعلی سرویس اضافه می‌شود.
                            </p>
                        </div>
                    </section>
                </main>

                <aside class="hidden xl:block">
                    <div class="sticky top-5 overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                        <div class="flex items-center justify-between border-b border-primary/10 bg-primary/[0.035] px-4 py-3.5">
                            <div>
                                <div class="text-sm font-semibold text-base-content">خلاصه تمدید</div>
                                <div class="mt-0.5 text-[11px] text-base-content/35">{{ $server->name ?: 'VPS' }}</div>
                            </div>
                            <x-icon name="lucide.receipt-text" class="!size-4.5 text-base-content/35" />
                        </div>
                        <div class="p-4">
                            <dl class="space-y-3 text-xs">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-base-content/35">دوره</dt>
                                    <dd class="font-medium text-base-content">{{ $selectedPeriod['label'] ?? '—' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-base-content/35">پایان فعلی</dt>
                                    <dd dir="ltr" class="technical-value text-[11px] font-medium text-base-content">{{ $currentExpiresAt ? \App\Support\Date\JalaliDateFormatter::dateTime($currentExpiresAt) : '—' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-base-content/35">پایان پس از تمدید</dt>
                                    <dd dir="ltr" class="technical-value text-[11px] font-semibold text-success">{{ $projectedExpiresAt ? \App\Support\Date\JalaliDateFormatter::dateTime($projectedExpiresAt) : '—' }}</dd>
                                </div>
                            </dl>
                            <div class="my-4 border-t border-base-300"></div>
                            @if($quoteError)
                                <div class="mb-3 rounded-lg bg-error/5 px-2.5 py-2 text-[11px] leading-5 text-error">{{ $quoteError }}</div>
                            @endif
                            <div>
                                <div class="text-[11px] text-base-content/35">مبلغ نهایی</div>
                                <div wire:loading.remove wire:target="loadQuote,selectPeriod">
                                    @if($quote !== [])
                                        <div class="mt-1 flex items-baseline gap-1.5 text-base-content">
                                            <span dir="ltr" class="text-xl font-semibold tracking-tight">{{ $this->formatToman((int) $quote['final_amount']) }}</span>
                                            <span class="text-xs text-base-content/40">تومان</span>
                                        </div>
                                    @else
                                        <div class="mt-1 text-sm text-base-content/35">—</div>
                                    @endif
                                </div>
                                <div wire:loading wire:target="loadQuote,selectPeriod" class="mt-1.5 flex items-center gap-2 text-xs text-base-content/35">
                                    <span class="loading loading-spinner loading-xs text-primary"></span>
                                    دریافت قیمت
                                </div>
                            </div>
                            <x-button
                                label="ادامه و پرداخت"
                                icon="lucide.credit-card"
                                wire:click="renew"
                                wire:target="renew"
                                spinner
                                :disabled="$quote === [] || $quoteError !== null"
                                class="btn-primary btn-sm mt-4 w-full rounded-xl"
                            />
                            <div class="mt-2.5 text-center text-[10px] leading-4 text-base-content/30">
                                اعتبار پیش‌فاکتور: {{ $quoteTtlMinutes }} دقیقه
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-base-300 bg-base-100/95 px-3 py-2.5 backdrop-blur xl:hidden">
                <div class="mx-auto flex max-w-2xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-[9px] text-base-content/30">مبلغ تمدید</div>
                        <div wire:loading.remove wire:target="loadQuote,selectPeriod">
                            @if($quote !== [])
                                <div class="flex items-baseline gap-1">
                                    <span dir="ltr" class="truncate text-sm font-semibold text-base-content">{{ $this->formatToman((int) $quote['final_amount']) }}</span>
                                    <span class="text-[10px] text-base-content/40">تومان</span>
                                </div>
                            @else
                                <span class="text-xs text-base-content/35">—</span>
                            @endif
                        </div>
                        <div wire:loading wire:target="loadQuote,selectPeriod" class="flex items-center gap-1.5 text-[10px] text-base-content/35">
                            <span class="loading loading-spinner loading-xs text-primary"></span>
                            دریافت قیمت
                        </div>
                    </div>
                    <x-button
                        label="پرداخت"
                        icon="lucide.credit-card"
                        wire:click="renew"
                        wire:target="renew"
                        spinner
                        :disabled="$quote === [] || $quoteError !== null"
                        class="btn-primary btn-sm min-w-28 rounded-xl"
                    />
                </div>
            </div>
        @endif
    </div>
</x-servers.workspace>
