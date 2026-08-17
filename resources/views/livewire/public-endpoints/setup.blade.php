@php
    $progress = \App\Support\PublicEndpoint\PublicEndpointSetupProgress::for(
        domain: $domain,
        dnsPreflight: $dnsPreflight,
        serverPreflight: $serverPreflight,
        operationActive: $operationActive,
        activationSuccess: $activationSuccess,
        activationError: $activationError,
        preflightError: $preflightError,
    );

    $dnsReady = ($dnsPreflight['ready'] ?? false) === true;
    $serverReady = ($serverPreflight['ready'] ?? false) === true;
    $readyForActivation = $progress['ready_for_activation'];
    $serviceUrl = trim($domain) !== '' ? 'https://'.trim($domain) : null;
@endphp

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
                    راه‌اندازی دامنه برای {{ $applicationName }}
                </h3>

                <p class="mt-1.5 max-w-2xl text-sm leading-7 text-base-content/55">
                    دامنه، DNS و آمادگی سرور بررسی می‌شوند و سپس HTTPS به‌صورت امن فعال می‌شود.
                </p>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-px border-t border-base-300 bg-base-300 sm:grid-cols-4">
            @foreach ($progress['steps'] as $step)
                @php
                    $stepPresentation = match ($step['state']) {
                        'complete' => [
                            'surface' => 'bg-base-100',
                            'icon' => 'bg-success/10 text-success',
                            'badge' => 'border-success/20 bg-success/10 text-success',
                            'label' => 'انجام شد',
                        ],
                        'running' => [
                            'surface' => 'bg-info/[0.035]',
                            'icon' => 'bg-info/10 text-info',
                            'badge' => 'border-info/20 bg-info/10 text-info',
                            'label' => 'در حال اجرا',
                        ],
                        'ready' => [
                            'surface' => 'bg-success/[0.025]',
                            'icon' => 'bg-success/10 text-success',
                            'badge' => 'border-success/20 bg-success/10 text-success',
                            'label' => 'آماده',
                        ],
                        'error' => [
                            'surface' => 'bg-error/[0.025]',
                            'icon' => 'bg-error/10 text-error',
                            'badge' => 'border-error/20 bg-error/10 text-error',
                            'label' => 'نیازمند بررسی',
                        ],
                        'current' => [
                            'surface' => 'bg-primary/[0.025]',
                            'icon' => 'bg-primary/10 text-primary',
                            'badge' => 'border-primary/20 bg-primary/10 text-primary',
                            'label' => 'مرحله فعلی',
                        ],
                        default => [
                            'surface' => 'bg-base-100',
                            'icon' => 'bg-base-200 text-base-content/35',
                            'badge' => 'border-base-300 bg-base-200/60 text-base-content/40',
                            'label' => 'در انتظار',
                        ],
                    };
                @endphp

                <div class="{{ $stepPresentation['surface'] }} px-4 py-4 sm:px-5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $stepPresentation['icon'] }}">
                            @if ($step['state'] === 'running')
                                <span class="loading loading-spinner loading-xs"></span>
                            @elseif ($step['state'] === 'complete')
                                <x-icon name="lucide.check" class="!size-3.5" />
                            @else
                                <x-icon :name="$step['icon']" class="!size-3.5" />
                            @endif
                        </span>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-base-content">
                                {{ $step['label'] }}
                            </p>

                            <span class="mt-1 inline-flex rounded-full border px-1.5 py-0.5 text-[9px] font-medium {{ $stepPresentation['badge'] }}">
                                {{ $stepPresentation['label'] }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($activationSuccess === null)
        <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
            <form
                wire:submit="runPreflight"
                class="px-5 py-5 sm:px-6"
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
                        label="بررسی دامنه و سرور"
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
                        <span dir="ltr" class="technical-value font-medium text-base-content/70">DNS Only</span>
                        قرار دهید.
                    </p>
                </div>
            </form>
        </section>
    @endif

    @if ($preflightError !== null)
        <div
            role="alert"
            class="flex flex-col gap-4 rounded-2xl border border-error/20 bg-error/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <x-icon
                    name="lucide.circle-alert"
                    class="mt-0.5 !size-4 shrink-0 text-error"
                />

                <div>
                    <p class="text-sm font-semibold text-base-content">
                        بررسی دامنه کامل نشد
                    </p>

                    <p class="mt-1 text-sm leading-7 text-base-content/60">
                        {{ $preflightError }}
                    </p>
                </div>
            </div>

            <x-button
                label="تلاش دوباره"
                icon="lucide.refresh-cw"
                wire:click="runPreflight"
                spinner="runPreflight"
                :disabled="$operationActive"
                wire:loading.attr="disabled"
                wire:target="runPreflight"
                class="btn-error btn-outline btn-sm shrink-0 rounded-xl"
            />
        </div>
    @endif

    @if ($dnsPreflight !== null && $activationSuccess === null)
        @php
            $resolvedIpv4 = $dnsPreflight['resolved_ipv4_addresses'] ?? [];
            $resolvedIpv6 = $dnsPreflight['resolved_ipv6_addresses'] ?? [];
            $expectedIpv4 = $dnsPreflight['server_ipv4_address'] ?? null;
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
                            {{ $dnsReady ? 'DNS تأیید شد' : 'DNS نیازمند اصلاح است' }}
                        </h4>

                        <span
                            @class([
                                'rounded-full border px-2 py-0.5 text-[11px] font-medium',
                                'border-success/20 bg-success/10 text-success' => $dnsReady,
                                'border-warning/20 bg-warning/10 text-warning' => ! $dnsReady,
                            ])
                        >
                            {{ $dnsReady ? 'آماده' : 'ادامه ممکن نیست' }}
                        </span>
                    </div>

                    <p class="mt-1.5 text-sm leading-6 text-base-content/55">
                        {{ $dnsReady
                            ? 'رکورد A مستقیماً به IP این سرور اشاره می‌کند.'
                            : 'رکورد A را روی IP این سرور تنظیم کنید، Proxy را بررسی کنید و سپس دوباره بررسی را اجرا کنید.' }}
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 border-t border-base-300 sm:grid-cols-2">
                <div class="px-5 py-3.5 sm:border-l sm:px-6">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] text-base-content/40">
                            IP مورد انتظار
                        </p>

                        @if (is_string($expectedIpv4) && $expectedIpv4 !== '')
                            <button
                                type="button"
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($expectedIpv4)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                class="inline-flex items-center gap-1 text-[10px] text-base-content/45 transition hover:text-primary"
                                aria-label="کپی IP سرور"
                            >
                                <x-icon name="lucide.copy" class="!size-3" />
                                <span x-text="copied ? 'کپی شد' : 'کپی'">کپی</span>
                            </button>
                        @endif
                    </div>

                    <p
                        dir="ltr"
                        class="technical-value mt-1 text-left text-sm font-medium text-base-content"
                    >
                        {{ $expectedIpv4 ?? '—' }}
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
                        {{ $resolvedIpv4 !== [] ? implode(', ', $resolvedIpv4) : 'رکورد A پیدا نشد' }}
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

                        <p class="mt-1 text-xs leading-6 text-base-content/50">
                            برای جلوگیری از هدایت بخشی از ترافیک به مقصد اشتباه، این رکورد را اصلاح یا حذف کنید.
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

    @if ($serverPreflight !== null && $activationSuccess === null)
        @php
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
                        {{ $serverReady ? 'سرور آماده HTTPS است' : 'سرور نیازمند بررسی است' }}
                    </h4>

                    <p class="mt-1.5 text-sm leading-6 text-base-content/55">
                        {{ $serverReady
                            ? 'پورت‌های لازم و ساختار سرویس برای اعمال امن HTTPS آماده‌اند.'
                            : 'تداخل پورت یا ساختار سرویس را برطرف کنید و بررسی را دوباره اجرا کنید.' }}
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
                            <span dir="ltr" class="technical-value text-sm font-medium">
                                Port {{ $port }}
                            </span>

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

                        @if (! $available)
                            <p
                                dir="ltr"
                                class="technical-value mt-1 text-left text-[11px] text-base-content/40"
                            >
                                {{ $info['owner'] ?? 'unknown' }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (! $serverReady)
                <div class="border-t border-base-300 px-5 py-3.5 sm:px-6">
                    <p class="text-xs leading-6 text-base-content/45">
                        ساختار شناسایی‌شده:
                        <span dir="ltr" class="technical-value font-medium text-base-content/65">
                            {{ $layoutState }}
                        </span>
                    </p>
                </div>
            @endif
        </section>
    @endif

    @if ($operationActive)
        <div
            role="status"
            aria-live="polite"
            class="flex items-start gap-3 rounded-2xl border border-info/20 bg-info/5 px-5 py-4"
        >
            <span class="loading loading-spinner loading-sm mt-0.5 shrink-0 text-info"></span>

            <div>
                <p class="text-sm font-semibold text-base-content">
                    در حال فعال‌سازی HTTPS
                </p>

                <p class="mt-1 text-sm leading-7 text-base-content/60">
                    Coreflare تنظیمات دامنه، Caddy و {{ $applicationName }} را اعمال و نتیجه را بررسی می‌کند. وضعیت به‌صورت خودکار بروزرسانی می‌شود.
                </p>
            </div>
        </div>
    @elseif ($readyForActivation)
        <div class="flex flex-col gap-4 rounded-2xl border border-success/20 bg-success/[0.04] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <x-icon
                    name="lucide.shield-check"
                    class="mt-0.5 !size-4 shrink-0 text-success"
                />

                <div>
                    <p class="text-sm font-semibold text-base-content">
                        همه پیش‌نیازها آماده‌اند
                    </p>

                    <p class="mt-1 text-xs leading-6 text-base-content/50">
                        DNS و سرور تأیید شدند. مرحله نهایی، فعال‌سازی HTTPS است.
                    </p>
                </div>
            </div>

            <x-button
                label="فعال‌سازی HTTPS"
                icon="lucide.lock-keyhole"
                wire:click="activateEndpoint"
                spinner="activateEndpoint"
                wire:loading.attr="disabled"
                wire:target="activateEndpoint"
                class="btn-success btn-sm shrink-0 rounded-xl"
            />
        </div>
    @endif

    @if ($activationError !== null)
        <div
            role="alert"
            class="flex flex-col gap-4 rounded-2xl border border-error/20 bg-error/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <x-icon
                    name="lucide.circle-alert"
                    class="mt-0.5 !size-4 shrink-0 text-error"
                />

                <div>
                    <p class="text-sm font-semibold text-base-content">
                        فعال‌سازی کامل نشد
                    </p>

                    <p class="mt-1 text-sm leading-7 text-base-content/60">
                        {{ $activationError }}
                    </p>
                </div>
            </div>

            @if ($readyForActivation)
                <x-button
                    label="تلاش مجدد برای HTTPS"
                    icon="lucide.refresh-cw"
                    wire:click="activateEndpoint"
                    spinner="activateEndpoint"
                    wire:loading.attr="disabled"
                    wire:target="activateEndpoint"
                    class="btn-error btn-outline btn-sm shrink-0 rounded-xl"
                />
            @else
                <x-button
                    label="بررسی مجدد"
                    icon="lucide.search-check"
                    wire:click="runPreflight"
                    spinner="runPreflight"
                    wire:loading.attr="disabled"
                    wire:target="runPreflight"
                    class="btn-error btn-outline btn-sm shrink-0 rounded-xl"
                />
            @endif
        </div>
    @endif

    @if ($activationSuccess !== null && $serviceUrl !== null)
        <section
            role="status"
            aria-live="polite"
            class="overflow-hidden rounded-2xl border border-success/20 bg-success/[0.035]"
        >
            <div class="px-5 py-5 sm:px-6">
                <div class="flex items-start gap-3.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                        <x-icon name="lucide.circle-check" class="!size-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h4 class="text-sm font-semibold text-base-content">
                            دامنه آماده استفاده است
                        </h4>

                        <p class="mt-1.5 text-sm leading-7 text-base-content/55">
                            DNS و HTTPS با موفقیت فعال شده‌اند و سرویس از طریق آدرس زیر در دسترس است.
                        </p>

                        <p
                            dir="ltr"
                            class="technical-value mt-3 break-all text-left text-sm font-medium text-base-content"
                        >
                            {{ $serviceUrl }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-success/15 bg-base-100/50 px-5 py-3.5 sm:px-6">
                <x-button
                    label="باز کردن سرویس"
                    icon="lucide.external-link"
                    :link="$serviceUrl"
                    external
                    class="btn-success btn-sm rounded-xl"
                />

                <button
                    type="button"
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText(@js($serviceUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
                    class="btn btn-ghost btn-sm rounded-xl"
                    aria-label="کپی آدرس سرویس"
                >
                    <x-icon name="lucide.copy" class="!size-4" />
                    <span x-text="copied ? 'کپی شد' : 'کپی آدرس'">کپی آدرس</span>
                </button>
            </div>
        </section>
    @endif
</div>
