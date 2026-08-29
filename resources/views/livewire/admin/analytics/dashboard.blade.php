<div class="space-y-5">
    <x-admin.page-header
        title="تحلیل محصول"
        description="نمای مدیریتی از جذب، خرید، تحویل سرور و فعال‌سازی کاربران بر اساس داده‌های PostHog."
        icon="lucide.chart-no-axes-combined"
    />

    <section class="flex flex-col gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium">بازه گزارش</p>
            <p class="mt-1 text-xs text-base-content/45">ترافیک داخلی و حساب‌های تست از گزارش حذف می‌شوند.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach([7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز'] as $range => $label)
                <button
                    type="button"
                    wire:click="setRange({{ $range }})"
                    class="btn btn-sm {{ $days === $range ? 'btn-primary' : 'btn-ghost border border-base-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    @if(! $report['available'])
        @php
            $unavailableMessage = match($report['unavailable_reason']) {
                'not_configured' => 'دسترسی خواندن گزارش PostHog هنوز روی محیط Production پیکربندی نشده است.',
                default => 'دریافت گزارش تحلیلی موقتاً ممکن نیست. عملکرد اصلی Coreflare تحت تأثیر قرار نگرفته است.',
            };
        @endphp

        <section class="rounded-2xl border border-warning/30 bg-warning/5 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <x-icon name="lucide.triangle-alert" class="!size-4 stroke-[1.8]" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold">گزارش تحلیلی در دسترس نیست</h2>
                    <p class="mt-1 text-xs leading-6 text-base-content/55">{{ $unavailableMessage }}</p>
                </div>
            </div>
        </section>
    @else
        @php($overview = $report['overview'])

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach([
                ['label' => 'بازدیدکننده', 'value' => $overview['visitors'], 'meta' => 'کاربر یکتا در Landing', 'icon' => 'lucide.users'],
                ['label' => 'احراز هویت', 'value' => $overview['authenticated'], 'meta' => number_format($overview['auth_conversion'], 1).'٪ تبدیل', 'icon' => 'lucide.log-in'],
                ['label' => 'سفارش', 'value' => $overview['orders'], 'meta' => number_format($overview['order_conversion'], 1).'٪ از Buy', 'icon' => 'lucide.receipt-text'],
                ['label' => 'پرداخت موفق', 'value' => $overview['payments'], 'meta' => number_format($overview['payment_conversion'], 1).'٪ از شروع پرداخت', 'icon' => 'lucide.badge-check'],
                ['label' => 'سرور آماده', 'value' => $overview['server_ready'], 'meta' => number_format($overview['server_ready_rate'], 1).'٪ از پرداخت موفق', 'icon' => 'lucide.server'],
                ['label' => 'فعال‌سازی', 'value' => $overview['activated'], 'meta' => number_format($overview['activation_rate'], 1).'٪ از سرور آماده', 'icon' => 'lucide.rocket'],
            ] as $metric)
                <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-base-content/45">{{ $metric['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight">{{ number_format($metric['value']) }}</p>
                            <p class="mt-1 text-[11px] text-base-content/40">{{ $metric['meta'] }}</p>
                        </div>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <x-icon :name="$metric['icon']" class="!size-3.5 stroke-[1.8]" />
                        </span>
                    </div>
                </section>
            @endforeach
        </div>

        @php
            $funnelSections = [
                'purchase' => ['title' => 'Purchase Funnel', 'description' => 'از ورود تا پرداخت موفق', 'icon' => 'lucide.shopping-cart'],
                'fulfillment' => ['title' => 'Fulfillment Funnel', 'description' => 'از پرداخت تا آماده‌شدن VPS', 'icon' => 'lucide.server-cog'],
                'activation' => ['title' => 'Activation Funnel', 'description' => 'از VPS آماده تا برنامه در حال اجرا', 'icon' => 'lucide.rocket'],
            ];
        @endphp

        <div class="grid gap-5 xl:grid-cols-3">
            @foreach($funnelSections as $key => $section)
                <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                    <div class="border-b border-base-300 p-4 sm:p-5">
                        <div class="flex items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <x-icon :name="$section['icon']" class="!size-4 stroke-[1.8]" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold" dir="ltr">{{ $section['title'] }}</h2>
                                <p class="mt-1 text-xs text-base-content/45">{{ $section['description'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-base-300">
                        @forelse($report['funnels'][$key] as $step)
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ $step['label'] }}</p>
                                        <p class="mt-1 font-mono text-[10px] text-base-content/35" dir="ltr">{{ $step['event'] }}</p>
                                    </div>
                                    <div class="text-left">
                                        <div class="text-sm font-semibold">{{ number_format($step['count']) }}</div>
                                        <div class="mt-0.5 text-[10px] text-base-content/40">{{ number_format($step['from_previous_percent'], 1) }}٪ مرحله قبل</div>
                                    </div>
                                </div>

                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-base-200">
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        style="width: {{ min(100, max(0, (float) $step['from_start_percent'])) }}%"
                                    ></div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-base-content/40">داده‌ای برای این Funnel ثبت نشده است.</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                <div class="border-b border-base-300 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold">منبع جذب · First Touch</h2>
                    <p class="mt-1 text-xs text-base-content/45">بر اساس UTM اولین ورودی کاربر؛ ورودی بدون UTM جدا نمایش داده می‌شود.</p>
                </div>
                <div class="divide-y divide-base-300">
                    @forelse($report['acquisition'] as $row)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <span class="truncate text-sm">{{ $row['label'] }}</span>
                            <strong class="text-sm">{{ number_format($row['value']) }}</strong>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-base-content/40">هنوز Attribution جدیدی در این بازه ثبت نشده است.</div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                <div class="border-b border-base-300 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold">وضعیت پرداخت‌ها</h2>
                    <p class="mt-1 text-xs text-base-content/45">تعداد eventهای پرداخت؛ برای تحلیل failure و cancellation.</p>
                </div>
                <div class="grid grid-cols-2 gap-px bg-base-300">
                    @forelse($report['payments'] as $row)
                        <div class="bg-base-100 p-4">
                            <p class="text-xs text-base-content/45">{{ $row['label'] }}</p>
                            <p class="mt-2 text-xl font-semibold">{{ number_format($row['value']) }}</p>
                        </div>
                    @empty
                        <div class="col-span-2 bg-base-100 p-6 text-center text-xs text-base-content/40">جزئیات outcome پرداخت در دسترس نیست.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach([
                ['title' => 'پرداخت موفق بر اساس Provider', 'rows' => $report['providers'], 'icon' => 'lucide.cloud'],
                ['title' => 'فعال‌سازی بر اساس برنامه', 'rows' => $report['applications'], 'icon' => 'lucide.box'],
            ] as $breakdown)
                <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                    <div class="flex items-center gap-3 border-b border-base-300 p-4 sm:p-5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/65">
                            <x-icon :name="$breakdown['icon']" class="!size-3.5 stroke-[1.8]" />
                        </span>
                        <h2 class="text-sm font-semibold">{{ $breakdown['title'] }}</h2>
                    </div>
                    <div class="divide-y divide-base-300">
                        @forelse($breakdown['rows'] as $row)
                            <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                <span class="text-sm">{{ $row['label'] }}</span>
                                <strong class="text-sm">{{ number_format($row['value']) }}</strong>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-base-content/40">داده‌ای در این بازه موجود نیست.</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <div class="flex items-start gap-2 rounded-2xl border border-base-300 bg-base-200/30 px-4 py-3 text-[11px] leading-6 text-base-content/45">
            <x-icon name="lucide.info" class="mt-1 !size-3.5 shrink-0 stroke-[1.8]" />
            <p>
                این صفحه فقط aggregateهای مدیریتی را نمایش می‌دهد. داده خام کاربر و کلید دسترسی PostHog وارد HTML یا state مرورگر نمی‌شوند. گزارش برای کاهش latency و فشار API به‌صورت کوتاه‌مدت cache می‌شود.
            </p>
        </div>
    @endif
</div>
