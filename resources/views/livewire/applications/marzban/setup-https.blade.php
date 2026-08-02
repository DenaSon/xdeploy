<x-card
    class="overflow-hidden border border-base-content/10
           bg-base-100/90 shadow-xl shadow-base-content/5
           backdrop-blur-xl"
>
    {{-- Header --}}
    <div class="flex items-start gap-4">
        <div
            class="flex size-12 shrink-0 items-center justify-center
                   rounded-2xl border border-primary/15
                   bg-primary/10 text-primary shadow-sm"
        >
            <x-icon
                name="o-globe-alt"
                class="size-6"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h3
                    class="text-base font-bold tracking-tight
                           text-base-content sm:text-lg"
                >
                    راه‌اندازی دامنه و HTTPS
                </h3>

                <span
                    class="badge badge-primary badge-outline
                           badge-sm font-medium"
                >
                    اتصال امن
                </span>
            </div>

            <p
                class="mt-1.5 max-w-2xl text-sm leading-7
                       text-base-content/60"
            >
                پیش از فعال‌سازی HTTPS، وضعیت DNS، پورت‌های موردنیاز
                و ساختار نصب Marzban به‌صورت خودکار بررسی می‌شود.
            </p>
        </div>
    </div>

    {{-- Domain form --}}
    <form
        wire:submit="runPreflight"
        class="mt-6 rounded-3xl border border-base-content/10
               bg-base-200/35 p-4 sm:p-5"
    >
        <div class="space-y-5">
            <x-input
                label="دامنه پنل"
                wire:model="domain"
                placeholder="panel.example.com"
                icon="o-globe-alt"
                hint="دامنه را بدون https://، مسیر یا شماره پورت وارد کنید."
                dir="ltr"
                autocomplete="off"
                class="bg-base-100"
            />

            <div
                class="flex flex-col gap-4 border-t
                       border-base-content/10 pt-4
                       sm:flex-row sm:items-center
                       sm:justify-between"
            >
                <div
                    class="flex items-start gap-2.5
                           text-xs leading-6 text-base-content/55"
                >
                    <x-icon
                        name="o-information-circle"
                        class="mt-0.5 size-4 shrink-0 text-info"
                    />

                    <span>
                        اگر از Cloudflare استفاده می‌کنید، Proxy دامنه را
                        موقتاً روی
                        <span class="font-semibold text-base-content/75">
                            DNS Only
                        </span>
                        قرار دهید.
                    </span>
                </div>

                <x-button
                    type="submit"
                    label="بررسی آمادگی"
                    icon="o-magnifying-glass"
                    spinner="runPreflight"
                    class="btn-primary min-h-11 w-full px-5
                           shadow-sm shadow-primary/20 sm:w-auto"
                />
            </div>
        </div>
    </form>

    {{-- Preflight error --}}
    @if ($preflightError !== null)
        <div
            role="alert"
            class="mt-6 flex items-start gap-3 rounded-2xl
                   border border-error/20 bg-error/5 p-4"
        >
            <div
                class="flex size-9 shrink-0 items-center justify-center
                       rounded-xl bg-error/10 text-error"
            >
                <x-icon
                    name="o-exclamation-circle"
                    class="size-5"
                />
            </div>

            <div class="min-w-0">
                <p class="text-sm font-semibold text-error">
                    بررسی دامنه ناموفق بود
                </p>

                <p class="mt-1 text-sm leading-7 text-base-content/70">
                    {{ $preflightError }}
                </p>
            </div>
        </div>
    @endif

    {{-- DNS preflight --}}
    @if ($dnsPreflight !== null)
        @php
            $ready = $dnsPreflight['ready'];
            $resolvedIpv4 = $dnsPreflight['resolved_ipv4_addresses'];
            $resolvedIpv6 = $dnsPreflight['resolved_ipv6_addresses'];

            $dnsPresentation = $ready
                ? [
                    'title' => 'DNS دامنه آماده است',
                    'description' => 'دامنه مستقیماً به این سرور متصل است و هیچ رکورد AAAA ناسازگاری شناسایی نشد.',
                    'icon' => 'o-check-circle',
                    'container' => 'border-success/20 bg-success/5',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'badge' => 'badge-success',
                    'badgeLabel' => 'آماده',
                ]
                : [
                    'title' => 'DNS هنوز آماده نیست',
                    'description' => 'رکورد A را اصلاح کنید، Proxy کلادفلر را خاموش کنید و رکورد AAAA ناسازگار را حذف کنید.',
                    'icon' => 'o-exclamation-triangle',
                    'container' => 'border-warning/20 bg-warning/5',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'badge' => 'badge-warning',
                    'badgeLabel' => 'نیازمند بررسی',
                ];
        @endphp

        <section
            @class([
                'mt-6 overflow-hidden rounded-3xl border',
                $dnsPresentation['container'],
            ])
            aria-live="polite"
        >
            {{-- DNS status --}}
            <div class="p-4 sm:p-5">
                <div class="flex items-start gap-4">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center',
                            'justify-center rounded-2xl',
                            $dnsPresentation['iconBackground'],
                            $dnsPresentation['iconColor'],
                        ])
                    >
                        <x-icon
                            :name="$dnsPresentation['icon']"
                            class="size-5"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4
                                class="text-sm font-semibold
                                       text-base-content"
                            >
                                {{ $dnsPresentation['title'] }}
                            </h4>

                            <span
                                @class([
                                    'badge badge-sm border-0 font-medium',
                                    $dnsPresentation['badge'],
                                ])
                            >
                                {{ $dnsPresentation['badgeLabel'] }}
                            </span>
                        </div>

                        <p
                            class="mt-1.5 max-w-2xl text-xs leading-6
                                   text-base-content/60"
                        >
                            {{ $dnsPresentation['description'] }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- DNS details --}}
            <div
                class="border-t border-base-content/10
                       bg-base-100/45 p-4 sm:p-5"
            >
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div
                        class="rounded-2xl border border-base-content/10
                               bg-base-100/85 p-4 shadow-sm"
                    >
                        <dt
                            class="flex items-center gap-2 text-xs
                                   font-medium text-base-content/50"
                        >
                            <span
                                class="size-1.5 rounded-full bg-primary"
                            ></span>

                            IP مورد انتظار سرور
                        </dt>

                        <dd
                            class="mt-2 break-all font-mono text-sm
                                   font-semibold tracking-tight
                                   text-base-content"
                            dir="ltr"
                        >
                            {{ $dnsPreflight['server_ipv4_address'] }}
                        </dd>
                    </div>

                    <div
                        class="rounded-2xl border border-base-content/10
                               bg-base-100/85 p-4 shadow-sm"
                    >
                        <dt
                            class="flex items-center gap-2 text-xs
                                   font-medium text-base-content/50"
                        >
                            <span
                                @class([
                                    'size-1.5 rounded-full',
                                    'bg-success' => $resolvedIpv4 !== [],
                                    'bg-error' => $resolvedIpv4 === [],
                                ])
                            ></span>

                            IPv4 شناسایی‌شده
                        </dt>

                        <dd
                            @class([
                                'mt-2 break-all font-mono text-sm',
                                'font-semibold tracking-tight',
                                'text-base-content' => $resolvedIpv4 !== [],
                                'text-error' => $resolvedIpv4 === [],
                            ])
                            dir="ltr"
                        >
                            {{ $resolvedIpv4 !== []
                                ? implode(', ', $resolvedIpv4)
                                : 'No A record' }}
                        </dd>
                    </div>
                </dl>

                @if ($resolvedIpv6 !== [])
                    <div
                        class="mt-3 flex items-start gap-3 rounded-2xl
                               border border-warning/20
                               bg-warning/5 p-4"
                    >
                        <x-icon
                            name="o-exclamation-triangle"
                            class="mt-0.5 size-5 shrink-0 text-warning"
                        />

                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold
                                       text-base-content"
                            >
                                رکورد AAAA ناسازگار شناسایی شد
                            </p>

                            <p
                                class="mt-1.5 break-all font-mono
                                       text-xs leading-6
                                       text-base-content/65"
                                dir="ltr"
                            >
                                {{ implode(', ', $resolvedIpv6) }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($ready && $serverPreflight === null)
                    <div
                        class="mt-4 flex items-center gap-3 rounded-2xl
                               border border-info/15 bg-info/5
                               px-4 py-3"
                    >
                        <span
                            class="loading loading-spinner
                                   loading-sm text-info"
                        ></span>

                        <p
                            class="text-xs leading-6
                                   text-base-content/60"
                        >
                            DNS تأیید شد؛ وضعیت پورت‌ها و ساختار سرور
                            در حال بررسی است.
                        </p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Server preflight --}}
    @if ($serverPreflight !== null)
        @php
            $serverReady = $serverPreflight['ready'];
            $portConflict = $serverPreflight['has_port_conflict'];

            $layoutPresentation = match (
                $serverPreflight['layout_state']
            ) {
                'supported' => [
                    'label' => 'پشتیبانی‌شده',
                    'badge' => 'badge-success',
                ],
                'missing' => [
                    'label' => 'نصب استاندارد پیدا نشد',
                    'badge' => 'badge-error',
                ],
                'unreadable' => [
                    'label' => 'قابل خواندن نیست',
                    'badge' => 'badge-error',
                ],
                'invalid_compose' => [
                    'label' => 'Compose نامعتبر',
                    'badge' => 'badge-error',
                ],
                default => [
                    'label' => 'پشتیبانی‌نشده',
                    'badge' => 'badge-warning',
                ],
            };

            $serverPresentation = match (true) {
                $serverReady => [
                    'title' => 'سرور برای HTTPS آماده است',
                    'description' => 'ساختار نصب Marzban تأیید شد و پورت‌های موردنیاز در دسترس هستند.',
                    'icon' => 'o-check-circle',
                    'container' => 'border-success/20 bg-success/5',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'badge' => 'badge-success',
                    'badgeLabel' => 'آماده',
                ],
                $portConflict => [
                    'title' => 'تداخل پورت شناسایی شد',
                    'description' => 'یک سرویس خارجی پورت موردنیاز را اشغال کرده است. xDeploy آن سرویس را متوقف یا حذف نمی‌کند.',
                    'icon' => 'o-exclamation-triangle',
                    'container' => 'border-error/20 bg-error/5',
                    'iconBackground' => 'bg-error/10',
                    'iconColor' => 'text-error',
                    'badge' => 'badge-error',
                    'badgeLabel' => 'دارای تداخل',
                ],
                default => [
                    'title' => 'سرور هنوز آماده نیست',
                    'description' => 'ساختار نصب فعلی برای اعمال امن تنظیمات HTTPS قابل استفاده نیست و هیچ فایلی تغییر نکرده است.',
                    'icon' => 'o-exclamation-triangle',
                    'container' => 'border-warning/20 bg-warning/5',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'badge' => 'badge-warning',
                    'badgeLabel' => 'نیازمند بررسی',
                ],
            };

            $ownerLabels = [
                'none' => 'بدون مالک',
                'xdeploy_caddy' => 'Caddy مدیریت‌شده xDeploy',
                'nginx' => 'Nginx',
                'apache' => 'Apache',
                'haproxy' => 'HAProxy',
                'caddy' => 'Caddy خارجی',
                'docker' => 'کانتینر Docker',
                'other' => 'سرویس دیگر',
                'unknown' => 'نامشخص',
            ];

            $portPresentations = [];

            foreach ([80, 443] as $port) {
                $portInfo = $serverPreflight['ports'][$port];

                $portPresentations[$port] = match ($portInfo['state']) {
                    'available' => [
                        'label' => 'آزاد',
                        'badge' => 'badge-success',
                        'icon' => 'o-check-circle',
                        'iconBackground' => 'bg-success/10',
                        'iconColor' => 'text-success',
                    ],
                    'managed' => [
                        'label' => 'مدیریت‌شده',
                        'badge' => 'badge-info',
                        'icon' => 'o-shield-check',
                        'iconBackground' => 'bg-info/10',
                        'iconColor' => 'text-info',
                    ],
                    'conflict' => [
                        'label' => 'دارای تداخل',
                        'badge' => 'badge-error',
                        'icon' => 'o-exclamation-triangle',
                        'iconBackground' => 'bg-error/10',
                        'iconColor' => 'text-error',
                    ],
                    default => [
                        'label' => 'نامشخص',
                        'badge' => 'badge-warning',
                        'icon' => 'o-question-mark-circle',
                        'iconBackground' => 'bg-warning/10',
                        'iconColor' => 'text-warning',
                    ],
                };
            }
        @endphp

        <section
            @class([
                'mt-6 overflow-hidden rounded-3xl border',
                $serverPresentation['container'],
            ])
            aria-live="polite"
        >
            {{-- Server status --}}
            <div class="p-4 sm:p-5">
                <div class="flex items-start gap-4">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center',
                            'justify-center rounded-2xl',
                            $serverPresentation['iconBackground'],
                            $serverPresentation['iconColor'],
                        ])
                    >
                        <x-icon
                            :name="$serverPresentation['icon']"
                            class="size-5"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4
                                class="text-sm font-semibold
                                       text-base-content"
                            >
                                {{ $serverPresentation['title'] }}
                            </h4>

                            <span
                                @class([
                                    'badge badge-sm border-0 font-medium',
                                    $serverPresentation['badge'],
                                ])
                            >
                                {{ $serverPresentation['badgeLabel'] }}
                            </span>
                        </div>

                        <p
                            class="mt-1.5 max-w-2xl text-xs leading-6
                                   text-base-content/60"
                        >
                            {{ $serverPresentation['description'] }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Server details --}}
            <div
                class="border-t border-base-content/10
                       bg-base-100/45 p-4 sm:p-5"
            >
                <div class="grid gap-3 sm:grid-cols-3">
                    {{-- Installation layout --}}
                    <div
                        class="rounded-2xl border border-base-content/10
                               bg-base-100/85 p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-medium
                                           text-base-content/50"
                                >
                                    ساختار نصب
                                </p>

                                <p
                                    class="mt-1 text-sm font-semibold
                                           text-base-content"
                                >
                                    Marzban Compose
                                </p>
                            </div>

                            <div
                                class="flex size-9 shrink-0 items-center
                                       justify-center rounded-xl
                                       bg-base-200 text-base-content/60"
                            >
                                <x-icon
                                    name="o-server-stack"
                                    class="size-4"
                                />
                            </div>
                        </div>

                        <span
                            @class([
                                'badge badge-sm mt-4 border-0 font-medium',
                                $layoutPresentation['badge'],
                            ])
                        >
                            {{ $layoutPresentation['label'] }}
                        </span>
                    </div>

                    {{-- Ports --}}
                    @foreach ([80, 443] as $port)
                        @php
                            $portInfo = $serverPreflight['ports'][$port];
                            $portPresentation = $portPresentations[$port];
                        @endphp

                        <div
                            class="rounded-2xl border
                                   border-base-content/10
                                   bg-base-100/85 p-4 shadow-sm"
                        >
                            <div
                                class="flex items-start
                                       justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-medium
                                               text-base-content/50"
                                    >
                                        {{ $port === 80 ? 'HTTP' : 'HTTPS' }}
                                    </p>

                                    <p
                                        class="mt-1 font-mono text-lg
                                               font-bold text-base-content"
                                        dir="ltr"
                                    >
                                        :{{ $port }}
                                    </p>
                                </div>

                                <div
                                    @class([
                                        'flex size-9 shrink-0 items-center',
                                        'justify-center rounded-xl',
                                        $portPresentation['iconBackground'],
                                        $portPresentation['iconColor'],
                                    ])
                                >
                                    <x-icon
                                        :name="$portPresentation['icon']"
                                        class="size-4"
                                    />
                                </div>
                            </div>

                            <div
                                class="mt-4 flex flex-wrap
                                       items-center gap-2"
                            >
                                <span
                                    @class([
                                        'badge badge-sm border-0',
                                        'font-medium',
                                        $portPresentation['badge'],
                                    ])
                                >
                                    {{ $portPresentation['label'] }}
                                </span>

                                @if ($portInfo['state'] !== 'available')
                                    <span
                                        class="truncate text-xs
                                               text-base-content/55"
                                        title="{{ $ownerLabels[$portInfo['owner']]
                                            ?? $ownerLabels['unknown'] }}"
                                    >
                                        {{ $ownerLabels[$portInfo['owner']]
                                            ?? $ownerLabels['unknown'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Activation --}}
                @if ($serverReady)
                    <div
                        class="mt-4 overflow-hidden rounded-3xl
                               border border-primary/20
                               bg-gradient-to-br from-primary/10
                               via-primary/5 to-base-100"
                    >
                        <div class="p-4 sm:p-5">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-11 shrink-0
                                           items-center justify-center
                                           rounded-2xl bg-primary/10
                                           text-primary"
                                >
                                    <x-icon
                                        name="o-shield-check"
                                        class="size-5"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h5
                                        class="text-sm font-semibold
                                               text-base-content"
                                    >
                                        آماده فعال‌سازی امن HTTPS
                                    </h5>

                                    <p
                                        class="mt-1.5 max-w-2xl
                                               text-xs leading-6
                                               text-base-content/60"
                                    >
                                        xDeploy تنظیمات جدید را اعتبارسنجی
                                        می‌کند، از فایل‌های فعلی نسخه پشتیبان
                                        می‌گیرد و تنها پس از تأیید گواهی TLS
                                        عملیات را موفق اعلام می‌کند.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-4 border-t
                                   border-primary/15 bg-base-100/35
                                   p-4 sm:flex-row sm:items-center
                                   sm:justify-between sm:p-5"
                        >
                            <div
                                class="flex items-center gap-2
                                       text-xs leading-6
                                       text-base-content/50"
                            >
                                <x-icon
                                    name="o-clock"
                                    class="size-4 shrink-0"
                                />

                                دریافت گواهی ممکن است چند دقیقه زمان ببرد.
                            </div>

                            <x-button
                                type="button"
                                label="فعال‌سازی HTTPS"
                                icon="o-lock-closed"
                                wire:click="activateHttps"
                                wire:confirm="فعال‌سازی HTTPS فایل‌های مدیریت‌شده Marzban را تغییر می‌دهد و سرویس‌ها را دوباره اجرا می‌کند. ادامه می‌دهید؟"
                                spinner="activateHttps"
                                class="btn-primary min-h-11 w-full
                                       px-5 shadow-sm
                                       shadow-primary/20 sm:w-auto"
                            />
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Activation error --}}
    @if ($activationError !== null)
        <div
            role="alert"
            class="mt-6 flex items-start gap-3 rounded-2xl
                   border border-error/20 bg-error/5 p-4"
        >
            <div
                class="flex size-9 shrink-0 items-center justify-center
                       rounded-xl bg-error/10 text-error"
            >
                <x-icon
                    name="o-exclamation-circle"
                    class="size-5"
                />
            </div>

            <div class="min-w-0">
                <p class="text-sm font-semibold text-error">
                    فعال‌سازی HTTPS ناموفق بود
                </p>

                <p class="mt-1 text-sm leading-7 text-base-content/70">
                    {{ $activationError }}
                </p>
            </div>
        </div>
    @endif
</x-card>
