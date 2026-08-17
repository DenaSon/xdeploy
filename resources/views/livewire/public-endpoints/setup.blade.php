<div
    class="space-y-4"
    @if ($operationActive)
        wire:poll.2s="pollOperation"
    @endif
>
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <header class="flex items-start gap-3.5 px-5 py-5 sm:px-6">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary">
                <x-icon name="lucide.globe-lock" class="!size-4.5" />
            </div>

            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-base-content">
                    راه‌اندازی دامنه و HTTPS برای {{ $applicationName }}
                </h3>

                <p class="mt-1.5 max-w-2xl text-sm leading-7 text-base-content/55">
                    ابتدا DNS و وضعیت سرور بررسی می‌شود؛ سپس xDeploy تنظیمات برنامه و Caddy را به‌صورت امن اعمال می‌کند.
                </p>
            </div>
        </header>

        <form
            wire:submit="runPreflight"
            class="border-t border-base-300 bg-base-200/20 px-5 py-5 sm:px-6"
        >
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <x-input
                    label="دامنه برنامه"
                    wire:model="domain"
                    placeholder="app.example.com"
                    icon="lucide.globe"
                    hint="دامنه را بدون https://، مسیر یا شماره پورت وارد کنید."
                    dir="ltr"
                    autocomplete="off"
                    :disabled="$operationActive"
                    wire:loading.attr="disabled"
                    wire:target="runPreflight"
                />

                <x-button
                    type="submit"
                    label="بررسی آمادگی"
                    icon="lucide.search-check"
                    spinner="runPreflight"
                    :disabled="$operationActive"
                    wire:loading.attr="disabled"
                    wire:target="runPreflight"
                    class="btn-primary btn-sm rounded-xl lg:mb-6"
                />
            </div>

            <div class="mt-4 flex items-start gap-2.5 text-xs leading-6 text-base-content/50">
                <x-icon
                    name="lucide.info"
                    class="mt-1 !size-3.5 shrink-0 text-info"
                />

                <p>
                    اگر از Cloudflare استفاده می‌کنید، Proxy را هنگام بررسی روی
                    <span
                        dir="ltr"
                        class="technical-value font-medium text-base-content/70"
                    >DNS Only</span>
                    قرار دهید.
                </p>
            </div>
        </form>
    </section>

    @if ($operationActive)
        <div
            role="status"
            aria-live="polite"
            class="flex items-start gap-3 rounded-2xl border border-info/20 bg-info/5 px-5 py-4"
        >
            <span class="loading loading-spinner loading-sm mt-0.5 shrink-0 text-info"></span>

            <div>
                <p class="text-sm font-semibold text-base-content">
                    فعال‌سازی HTTPS در پس‌زمینه
                </p>

                <p class="mt-1 text-sm leading-7 text-base-content/60">
                    تنظیمات دامنه، Caddy و {{ $applicationName }} در صف provisioning در حال اعمال و بررسی است.
                    می‌توانید این صفحه را باز نگه دارید؛ وضعیت عملیات به‌صورت خودکار بروزرسانی می‌شود.
                </p>
            </div>
        </div>
    @endif

    @if ($preflightError !== null)
        <div
            role="alert"
            class="flex items-start gap-3 rounded-2xl border border-error/20 bg-error/5 px-5 py-4"
        >
            <x-icon
                name="lucide.circle-alert"
                class="mt-0.5 !size-4 shrink-0 text-error"
            />

            <div>
                <p class="text-sm font-semibold text-error">
                    بررسی دامنه ناموفق بود
                </p>

                <p class="mt-1 text-sm leading-7 text-base-content/60">
                    {{ $preflightError }}
                </p>
            </div>
        </div>
    @endif

    @if ($dnsPreflight !== null)
        @php
            $dnsReady = ($dnsPreflight['ready'] ?? false) === true;
            $resolvedIpv4 = $dnsPreflight['resolved_ipv4_addresses'] ?? [];
            $resolvedIpv6 = $dnsPreflight['resolved_ipv6_addresses'] ?? [];
        @endphp

        <section
            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            aria-live="polite"
        >
            <header class="flex items-start gap-3.5 px-5 py-4 sm:px-6">
                <div
                    @class([
                        'flex size-9 shrink-0 items-center justify-center rounded-xl',
                        'bg-success/10 text-success' => $dnsReady,
                        'bg-warning/10 text-warning' => ! $dnsReady,
                    ])
                >
                    <x-icon
                        :name="$dnsReady ? 'lucide.circle-check' : 'lucide.triangle-alert'"
                        class="!size-4"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="text-sm font-semibold text-base-content">
                            {{ $dnsReady ? 'DNS دامنه آماده است' : 'DNS هنوز آماده نیست' }}
                        </h4>

                        <span
                            @class([
                                'rounded-full border px-2 py-0.5 text-[11px] font-medium',
                                'border-success/20 bg-success/10 text-success' => $dnsReady,
                                'border-warning/20 bg-warning/10 text-warning' => ! $dnsReady,
                            ])
                        >
                            {{ $dnsReady ? 'آماده' : 'نیازمند بررسی' }}
                        </span>
                    </div>

                    <p class="mt-1.5 text-sm leading-6 text-base-content/55">
                        {{ $dnsReady
                            ? 'رکورد A مستقیماً به این سرور اشاره می‌کند و رکورد AAAA ناسازگار وجود ندارد.'
                            : 'رکورد A، وضعیت Proxy و رکوردهای AAAA را بررسی کنید.' }}
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 border-t border-base-300 sm:grid-cols-2">
                <div class="px-5 py-3.5 sm:border-l sm:px-6">
                    <p class="text-[11px] text-base-content/40">
                        IP مورد انتظار سرور
                    </p>

                    <p
                        dir="ltr"
                        class="technical-value mt-1 text-left text-sm font-medium text-base-content"
                    >
                        {{ $dnsPreflight['server_ipv4_address'] ?? '—' }}
                    </p>
                </div>

                <div class="border-t border-base-300 px-5 py-3.5 sm:border-t-0 sm:px-6">
                    <p class="text-[11px] text-base-content/40">
                        IPv4 شناسایی‌شده
                    </p>

                    <p
                        dir="ltr"
                        class="technical-value mt-1 text-left text-sm font-medium text-base-content"
                    >
                        {{ $resolvedIpv4 !== [] ? implode(', ', $resolvedIpv4) : 'No A record' }}
                    </p>
                </div>
            </div>

            @if ($resolvedIpv6 !== [])
                <div class="flex items-start gap-3 border-t border-warning/15 bg-warning/[0.035] px-5 py-4 sm:px-6">
                    <x-icon
                        name="lucide.triangle-alert"
                        class="mt-0.5 !size-4 shrink-0 text-warning"
                    />

                    <div class="min-w-0">
                        <p class="text-sm font-medium text-base-content">
                            رکورد AAAA ناسازگار شناسایی شد
                        </p>

                        <p
                            dir="ltr"
                            class="technical-value mt-1 break-all text-left text-xs leading-6 text-base-content/55"
                        >
                            {{ implode(', ', $resolvedIpv6) }}
                        </p>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if ($serverPreflight !== null)
        @php
            $serverReady = ($serverPreflight['ready'] ?? false) === true;
            $port80 = $serverPreflight['ports'][80] ?? null;
            $port443 = $serverPreflight['ports'][443] ?? null;
            $layoutState = $serverPreflight['layout_state'] ?? 'unknown';
        @endphp

        <section
            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            aria-live="polite"
        >
            <header class="flex items-start gap-3.5 px-5 py-4 sm:px-6">
                <div
                    @class([
                        'flex size-9 shrink-0 items-center justify-center rounded-xl',
                        'bg-success/10 text-success' => $serverReady,
                        'bg-warning/10 text-warning' => ! $serverReady,
                    ])
                >
                    <x-icon
                        :name="$serverReady ? 'lucide.circle-check' : 'lucide.triangle-alert'"
                        class="!size-4"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-base-content">
                        {{ $serverReady ? 'سرور برای HTTPS آماده است' : 'سرور نیازمند بررسی است' }}
                    </h4>

                    <p class="mt-1.5 text-sm leading-6 text-base-content/55">
                        ساختار نصب:
                        <span dir="ltr" class="technical-value">
                            {{ $layoutState }}
                        </span>
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-2 border-t border-base-300 bg-base-200/20">
                @foreach ([80 => $port80, 443 => $port443] as $port => $info)
                    @php
                        $available = ($info['available_for_xdeploy'] ?? false) === true;
                    @endphp

                    <div
                        @class([
                            'px-5 py-3.5 sm:px-6',
                            'border-l border-base-300' => $port === 80,
                        ])
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                dir="ltr"
                                class="technical-value text-sm font-medium"
                            >Port {{ $port }}</span>

                            <span
                                @class([
                                    'rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                    'border-success/20 bg-success/10 text-success' => $available,
                                    'border-error/20 bg-error/10 text-error' => ! $available,
                                ])
                            >
                                {{ $available ? 'آماده' : 'تداخل' }}
                            </span>
                        </div>

                        <p
                            dir="ltr"
                            class="technical-value mt-1 text-left text-[11px] text-base-content/40"
                        >
                            {{ $info['owner'] ?? 'unknown' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($activationSuccess !== null)
        <div
            role="status"
            class="flex items-start gap-3 rounded-2xl border border-success/20 bg-success/5 px-5 py-4"
        >
            <x-icon
                name="lucide.circle-check"
                class="mt-0.5 !size-4 shrink-0 text-success"
            />

            <p class="text-sm leading-7 text-base-content/60">
                {{ $activationSuccess }}
            </p>
        </div>
    @endif

    @if ($activationError !== null)
        <div
            role="alert"
            class="flex items-start gap-3 rounded-2xl border border-error/20 bg-error/5 px-5 py-4"
        >
            <x-icon
                name="lucide.circle-alert"
                class="mt-0.5 !size-4 shrink-0 text-error"
            />

            <p class="text-sm leading-7 text-base-content/60">
                {{ $activationError }}
            </p>
        </div>
    @endif

    @if (
        ($dnsPreflight['ready'] ?? false) === true
        && ($serverPreflight['ready'] ?? false) === true
    )
        <div class="flex items-center justify-between gap-4 rounded-2xl border border-success/20 bg-success/[0.04] px-5 py-4">
            <div class="flex items-start gap-3">
                <x-icon
                    name="lucide.shield-check"
                    class="mt-0.5 !size-4 shrink-0 text-success"
                />

                <div>
                    <p class="text-sm font-semibold text-base-content">
                        همه بررسی‌ها با موفقیت انجام شد
                    </p>

                    <p class="mt-1 text-xs leading-6 text-base-content/45">
                        اکنون Coreflare می‌تواند Caddy و تنظیمات {{ $applicationName }} را به‌صورت امن اعمال کند.
                    </p>
                </div>
            </div>

            <x-button
                :label="$operationActive ? 'در حال فعال‌سازی…' : 'فعال‌سازی HTTPS'"
                icon="lucide.lock-keyhole"
                wire:click="activateEndpoint"
                spinner="activateEndpoint"
                :disabled="$operationActive"
                wire:loading.attr="disabled"
                wire:target="activateEndpoint"
                class="btn-success btn-sm shrink-0 rounded-xl"
            />
        </div>
    @endif
</div>
