<div class="space-y-5" wire:poll.30s>
    <x-admin.page-header
        title="وضعیت ارائه‌دهندگان ابری"
        description="نمایش زنده Health، وضعیت عملیاتی و آخرین سیگنال‌های Providerها. این صفحه فقط خواندنی است."
        icon="lucide.activity"
    />

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">سالم</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $summary['healthy'] }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">اختلال نسبی</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $summary['degraded'] }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">در دسترس نیست</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $summary['unavailable'] }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">نامشخص</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $summary['unknown'] }}</div>
        </div>
    </section>

    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3 text-sm text-base-content/60 sm:px-5">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <span>
                Probe دوره‌ای:
                <span class="font-medium {{ $probeEnabled ? 'text-success' : 'text-base-content/45' }}">
                    {{ $probeEnabled ? 'فعال' : 'غیرفعال' }}
                </span>
            </span>
            <span>
                اعتبار Health state:
                <span class="font-mono" dir="ltr">{{ (int) ceil($stateTtlSeconds / 60) }} min</span>
            </span>
            <span class="text-xs text-base-content/40">صفحه هر ۳۰ ثانیه به‌روزرسانی می‌شود.</span>
        </div>
    </div>

    <section class="grid gap-4 xl:grid-cols-2">
        @foreach($providers as $provider)
            @php($snapshot = $provider['snapshot'])

            <article
                wire:key="admin-cloud-provider-{{ $provider['key'] }}"
                class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            >
                <header class="flex flex-col gap-3 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $provider['name'] }}</h2>
                            <span class="badge badge-ghost badge-sm font-mono" dir="ltr">{{ $provider['key'] }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="badge badge-sm {{ $provider['enabled'] ? 'badge-success badge-outline' : 'badge-ghost' }}">
                                {{ $provider['enabled'] ? 'عملیاتی' : 'غیرفعال' }}
                            </span>
                            <span class="badge badge-sm {{ $provider['purchase_enabled'] ? 'badge-info badge-outline' : 'badge-ghost' }}">
                                {{ $provider['purchase_enabled'] ? 'خرید فعال' : 'خرید غیرفعال' }}
                            </span>
                        </div>
                    </div>

                    <span class="badge {{ $provider['status_class'] }} gap-2 self-start sm:self-auto">
                        <span class="size-1.5 rounded-full bg-current"></span>
                        {{ $provider['status_label'] }}
                    </span>
                </header>

                @if($snapshot)
                    <div class="grid grid-cols-2 gap-px bg-base-300 sm:grid-cols-4">
                        <div class="bg-base-100 p-4">
                            <div class="text-xs text-base-content/45">Latency اخیر</div>
                            <div class="mt-1 font-mono text-sm" dir="ltr">
                                {{ $snapshot->lastLatencyMs !== null ? number_format($snapshot->lastLatencyMs, 2) . ' ms' : '—' }}
                            </div>
                        </div>
                        <div class="bg-base-100 p-4">
                            <div class="text-xs text-base-content/45">Failure متوالی</div>
                            <div class="mt-1 font-mono text-sm" dir="ltr">{{ $snapshot->consecutiveAvailabilityFailures }}</div>
                        </div>
                        <div class="bg-base-100 p-4">
                            <div class="text-xs text-base-content/45">آخرین مشاهده</div>
                            <div class="mt-1 text-sm" title="{{ $snapshot->lastObservedAt->format('Y-m-d H:i:s') }}">
                                {{ $snapshot->lastObservedAt->diffForHumans() }}
                            </div>
                        </div>
                        <div class="bg-base-100 p-4">
                            <div class="text-xs text-base-content/45">تغییر وضعیت</div>
                            <div class="mt-1 text-sm" @if($snapshot->statusChangedAt) title="{{ $snapshot->statusChangedAt->format('Y-m-d H:i:s') }}" @endif>
                                {{ $snapshot->statusChangedAt?->diffForHumans() ?? '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
                        <div class="space-y-3">
                            <div>
                                <div class="text-xs text-base-content/45">آخرین موفقیت</div>
                                <div class="mt-1 text-sm" @if($snapshot->lastSuccessAt) title="{{ $snapshot->lastSuccessAt->format('Y-m-d H:i:s') }}" @endif>
                                    {{ $snapshot->lastSuccessAt?->diffForHumans() ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-base-content/45">آخرین عملیات</div>
                                <div class="mt-1 font-mono text-xs text-base-content/70" dir="ltr">
                                    {{ $snapshot->lastOperation ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <div class="text-xs text-base-content/45">آخرین خطا</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                    <span>{{ $provider['error_label'] ?? '—' }}</span>
                                    @if($snapshot->lastErrorHttpStatus !== null)
                                        <span class="badge badge-ghost badge-sm font-mono" dir="ltr">
                                            HTTP {{ $snapshot->lastErrorHttpStatus }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-base-content/45">زمان آخرین خطا</div>
                                <div class="mt-1 text-sm" @if($snapshot->lastFailureAt) title="{{ $snapshot->lastFailureAt->format('Y-m-d H:i:s') }}" @endif>
                                    {{ $snapshot->lastFailureAt?->diffForHumans() ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-5 text-sm text-base-content/50">
                        هنوز Health signal معتبری برای این Provider ثبت نشده است.
                        @if(! $provider['enabled'])
                            Provider از نظر عملیاتی غیرفعال است و probe دوره‌ای آن را بررسی نمی‌کند.
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </section>
</div>
