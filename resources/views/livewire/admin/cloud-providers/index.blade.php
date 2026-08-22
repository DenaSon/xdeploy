<div class="space-y-5" wire:poll.30s>
    <x-admin.page-header
        title="وضعیت ارائه‌دهندگان ابری"
        description="سلامت فنی Providerها، آمادگی خرید و آخرین سیگنال‌های ثبت‌شده. این صفحه فقط خواندنی است."
        icon="lucide.activity"
    />

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">سلامت سرویس</h2>
                    <p class="mt-1 text-xs text-base-content/45">وضعیت فنی API و ارتباط با Provider</p>
                </div>
                <x-icon name="lucide.heart-pulse" class="!size-5 text-base-content/35" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-xl bg-base-200/45 p-3">
                    <div class="text-[11px] text-base-content/45">سالم</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums">{{ $healthSummary['healthy'] }}</div>
                </div>
                <div class="rounded-xl bg-base-200/45 p-3">
                    <div class="text-[11px] text-base-content/45">ناپایدار</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums">{{ $healthSummary['degraded'] }}</div>
                </div>
                <div class="rounded-xl bg-base-200/45 p-3">
                    <div class="text-[11px] text-base-content/45">خارج از دسترس</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums">{{ $healthSummary['unavailable'] }}</div>
                </div>
                <div class="rounded-xl bg-base-200/45 p-3">
                    <div class="text-[11px] text-base-content/45">بدون داده</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums">{{ $healthSummary['unknown'] }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">آمادگی خرید</h2>
                    <p class="mt-1 text-xs text-base-content/45">آیا Coreflare می‌تواند سفارش جدید ایجاد کند؟</p>
                </div>
                <x-icon name="lucide.shopping-cart" class="!size-5 text-base-content/35" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <div class="rounded-xl bg-success/10 p-3">
                    <div class="text-[11px] text-base-content/45">آماده خرید</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-success">{{ $purchaseSummary['ready'] }}</div>
                </div>
                <div class="rounded-xl bg-base-200/45 p-3">
                    <div class="text-[11px] text-base-content/45">خرید مسدود</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums">{{ $purchaseSummary['blocked'] }}</div>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-base-content/50">
            <span class="inline-flex items-center gap-1.5">
                <span class="size-1.5 rounded-full {{ $probeEnabled ? 'bg-success' : 'bg-base-content/25' }}"></span>
                Probe دوره‌ای: <strong class="font-medium text-base-content/70">{{ $probeEnabled ? 'فعال' : 'غیرفعال' }}</strong>
            </span>
            <span>
                TTL وضعیت:
                <span class="font-mono text-base-content/70" dir="ltr">{{ (int) ceil($stateTtlSeconds / 60) }} min</span>
            </span>
            <span>به‌روزرسانی صفحه: هر ۳۰ ثانیه</span>
        </div>
    </div>

    <section class="grid gap-4 xl:grid-cols-2">
        @foreach($providers as $provider)
            @php($snapshot = $provider['snapshot'])

            <article
                wire:key="admin-cloud-provider-{{ $provider['key'] }}"
                class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            >
                <header class="border-b border-base-300 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold">{{ $provider['name'] }}</h2>
                                <span class="badge badge-ghost badge-sm font-mono" dir="ltr">{{ $provider['key'] }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-base-content/45">
                                <span>
                                    عملیاتی:
                                    <strong class="font-medium {{ $provider['enabled'] ? 'text-success' : 'text-base-content/50' }}">
                                        {{ $provider['enabled'] ? 'فعال' : 'غیرفعال' }}
                                    </strong>
                                </span>
                                <span class="text-base-content/20">•</span>
                                <span>
                                    خرید در تنظیمات:
                                    <strong class="font-medium {{ $provider['purchase_enabled'] ? 'text-success' : 'text-base-content/50' }}">
                                        {{ $provider['purchase_enabled'] ? 'فعال' : 'غیرفعال' }}
                                    </strong>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <span class="badge {{ $provider['status_class'] }} gap-1.5">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                Health: {{ $provider['status_label'] }}
                            </span>
                            <span class="badge {{ $provider['purchase_readiness_class'] }} gap-1.5">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                {{ $provider['purchase_readiness_label'] }}
                            </span>
                        </div>
                    </div>

                    @if($provider['purchase_readiness_reason'])
                        <div class="mt-3 rounded-xl bg-base-200/50 px-3 py-2 text-xs text-base-content/60">
                            {{ $provider['purchase_readiness_reason'] }}
                        </div>
                    @endif
                </header>

                @if($snapshot)
                    <div class="grid grid-cols-1 border-b border-base-300 sm:grid-cols-3 sm:divide-x sm:divide-x-reverse sm:divide-base-300">
                        <div class="p-4">
                            <div class="text-[11px] text-base-content/45">Latency اخیر</div>
                            <div class="mt-1 font-mono text-sm" dir="ltr">
                                {{ $snapshot->lastLatencyMs !== null ? number_format($snapshot->lastLatencyMs, 2) . ' ms' : '—' }}
                            </div>
                        </div>
                        <div class="border-t border-base-300 p-4 sm:border-t-0">
                            <div class="text-[11px] text-base-content/45">آخرین مشاهده</div>
                            <div class="mt-1 text-sm" title="{{ $snapshot->lastObservedAt->format('Y-m-d H:i:s') }}">
                                {{ $snapshot->lastObservedAt->diffForHumans() }}
                            </div>
                        </div>
                        <div class="border-t border-base-300 p-4 sm:border-t-0">
                            <div class="text-[11px] text-base-content/45">Failure متوالی</div>
                            <div class="mt-1 font-mono text-sm" dir="ltr">{{ $snapshot->consecutiveAvailabilityFailures }}</div>
                        </div>
                    </div>

                    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-5">
                        <section>
                            <h3 class="text-xs font-semibold text-base-content/70">آخرین سیگنال موفق</h3>
                            <dl class="mt-3 space-y-3">
                                <div>
                                    <dt class="text-[11px] text-base-content/40">آخرین موفقیت</dt>
                                    <dd class="mt-1 text-sm" @if($snapshot->lastSuccessAt) title="{{ $snapshot->lastSuccessAt->format('Y-m-d H:i:s') }}" @endif>
                                        {{ $snapshot->lastSuccessAt?->diffForHumans() ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] text-base-content/40">آخرین عملیات</dt>
                                    <dd class="mt-1 font-mono text-xs text-base-content/70" dir="ltr">
                                        {{ $snapshot->lastOperation ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] text-base-content/40">آخرین تغییر Health</dt>
                                    <dd class="mt-1 text-sm" @if($snapshot->statusChangedAt) title="{{ $snapshot->statusChangedAt->format('Y-m-d H:i:s') }}" @endif>
                                        {{ $snapshot->statusChangedAt?->diffForHumans() ?? '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section class="border-t border-base-300 pt-4 sm:border-r sm:border-t-0 sm:pr-5 sm:pt-0">
                            <h3 class="text-xs font-semibold text-base-content/70">آخرین خطا</h3>

                            @if($snapshot->lastFailureAt)
                                <dl class="mt-3 space-y-3">
                                    <div>
                                        <dt class="text-[11px] text-base-content/40">نوع خطا</dt>
                                        <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                            <span>{{ $provider['error_label'] ?? '—' }}</span>
                                            @if($snapshot->lastErrorHttpStatus !== null)
                                                <span class="badge badge-ghost badge-sm font-mono" dir="ltr">HTTP {{ $snapshot->lastErrorHttpStatus }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[11px] text-base-content/40">زمان</dt>
                                        <dd class="mt-1 text-sm" title="{{ $snapshot->lastFailureAt->format('Y-m-d H:i:s') }}">
                                            {{ $snapshot->lastFailureAt->diffForHumans() }}
                                        </dd>
                                    </div>
                                </dl>
                            @else
                                <div class="mt-3 rounded-xl bg-success/10 px-3 py-3 text-xs text-base-content/55">
                                    خطایی در snapshot فعلی ثبت نشده است.
                                </div>
                            @endif
                        </section>
                    </div>
                @else
                    <div class="p-5">
                        <div class="rounded-xl bg-base-200/45 px-4 py-4 text-sm text-base-content/55">
                            هنوز سیگنال Health معتبری برای این Provider ثبت نشده است.
                            @if(! $provider['enabled'])
                                Provider عملیاتی غیرفعال است و probe دوره‌ای آن را بررسی نمی‌کند.
                            @endif
                        </div>
                    </div>
                @endif
            </article>
        @endforeach
    </section>
</div>
