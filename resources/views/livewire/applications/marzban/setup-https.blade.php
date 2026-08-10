<div class="space-y-4">

    {{-- HTTPS setup --}}
    <section
        class="overflow-hidden rounded-2xl
               border border-base-300
               bg-base-100"
    >
        {{-- Header --}}
        <header
            class="flex items-start gap-3.5
                   px-5 py-5
                   sm:px-6"
        >
            <div
                class="flex size-10 shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-primary/8
                       text-primary"
            >
                <x-icon
                    name="lucide.globe-lock"
                    class="size-4.5"
                />
            </div>

            <div class="min-w-0">
                <div
                    class="flex flex-wrap
                           items-center gap-2"
                >
                    <h3
                        class="text-sm font-semibold
                               text-base-content"
                    >
                        راه‌اندازی دامنه و HTTPS
                    </h3>

                    <span
                        class="inline-flex items-center
                               gap-1.5 rounded-full
                               border border-primary/15
                               bg-primary/8
                               px-2 py-0.5
                               text-[11px] font-medium
                               text-primary"
                    >
                        <x-icon
                            name="lucide.shield-check"
                            class="size-3"
                        />

                        اتصال امن
                    </span>
                </div>

                <p
                    class="mt-1.5 max-w-2xl
                           text-sm leading-7
                           text-base-content/55"
                >
                    پیش از فعال‌سازی HTTPS، وضعیت DNS،
                    پورت‌های موردنیاز و ساختار نصب Marzban
                    به‌صورت خودکار بررسی می‌شود.
                </p>
            </div>
        </header>

        {{-- Domain form --}}
        <form
            wire:submit="runPreflight"
            class="border-t border-base-300
                   bg-base-200/20
                   px-5 py-5
                   sm:px-6"
        >
            <div
                class="grid grid-cols-1 gap-4
                       lg:grid-cols-[minmax(0,1fr)_auto]
                       lg:items-end"
            >
                <x-input
                    label="دامنه پنل"
                    wire:model="domain"
                    placeholder="panel.example.com"
                    icon="lucide.globe"
                    hint="دامنه را بدون https://، مسیر یا شماره پورت وارد کنید."
                    dir="ltr"
                    autocomplete="off"
                    wire:loading.attr="disabled"
                    wire:target="runPreflight"
                />

                <x-button
                    type="submit"
                    label="بررسی آمادگی"
                    icon="lucide.search-check"
                    spinner="runPreflight"
                    wire:loading.attr="disabled"
                    wire:target="runPreflight"
                    class="btn-primary btn-sm
                           rounded-xl
                           lg:mb-6"
                />
            </div>

            {{-- Cloudflare hint --}}
            <div
                class="mt-4 flex items-start gap-2.5
                       text-xs leading-6
                       text-base-content/50"
            >
                <x-icon
                    name="lucide.info"
                    class="mt-1 size-3.5
                           shrink-0 text-info"
                />

                <p>
                    اگر از Cloudflare استفاده می‌کنید،
                    Proxy دامنه را موقتاً روی
                    <span
                        dir="ltr"
                        class="technical-value
                               font-medium
                               text-base-content/70"
                    >
                        DNS Only
                    </span>
                    قرار دهید.
                </p>
            </div>
        </form>
    </section>

    {{-- Preflight error --}}
    @if ($preflightError !== null)

        <div
            role="alert"
            class="flex items-start gap-3
                   rounded-2xl
                   border border-error/20
                   bg-error/5
                   px-5 py-4"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-error/10
                       text-error"
            >
                <x-icon
                    name="lucide.circle-alert"
                    class="size-4"
                />
            </div>

            <div class="min-w-0">
                <p
                    class="text-sm font-semibold
                           text-error"
                >
                    بررسی دامنه ناموفق بود
                </p>

                <p
                    class="mt-1 text-sm leading-7
                           text-base-content/60"
                >
                    {{ $preflightError }}
                </p>
            </div>
        </div>

    @endif

    {{-- DNS preflight --}}
    @if ($dnsPreflight !== null)

        @php
            $ready = $dnsPreflight['ready'];

            $resolvedIpv4 =
                $dnsPreflight['resolved_ipv4_addresses'];

            $resolvedIpv6 =
                $dnsPreflight['resolved_ipv6_addresses'];

            $dnsPresentation = $ready
                ? [
                    'title' => 'DNS دامنه آماده است',
                    'description' =>
                        'دامنه مستقیماً به این سرور متصل است و هیچ رکورد AAAA ناسازگاری شناسایی نشد.',
                    'icon' => 'lucide.circle-check',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'statusClasses' =>
                        'border-success/20 bg-success/10 text-success',
                    'dot' => 'bg-success',
                    'label' => 'آماده',
                ]
                : [
                    'title' => 'DNS هنوز آماده نیست',
                    'description' =>
                        'رکورد A را اصلاح کنید، Proxy کلادفلر را خاموش کنید و رکورد AAAA ناسازگار را حذف کنید.',
                    'icon' => 'lucide.triangle-alert',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'statusClasses' =>
                        'border-warning/20 bg-warning/10 text-warning',
                    'dot' => 'bg-warning',
                    'label' => 'نیازمند بررسی',
                ];
        @endphp

        <section
            class="overflow-hidden rounded-2xl
                   border border-base-300
                   bg-base-100"
            aria-live="polite"
        >
            {{-- DNS status --}}
            <header
                class="flex items-start gap-3.5
                       px-5 py-4
                       sm:px-6"
            >
                <div
                    class="flex size-9 shrink-0
                           items-center justify-center
                           rounded-xl
                           {{ $dnsPresentation['iconBackground'] }}
                           {{ $dnsPresentation['iconColor'] }}"
                >
                    <x-icon
                        :name="$dnsPresentation['icon']"
                        class="size-4"
                    />
                </div>

                <div class="min-w-0 flex-1">

                    <div
                        class="flex flex-wrap
                               items-center gap-2"
                    >
                        <h4
                            class="text-sm font-semibold
                                   text-base-content"
                        >
                            {{ $dnsPresentation['title'] }}
                        </h4>

                        <span
                            class="inline-flex items-center
                                   gap-1.5 rounded-full
                                   border px-2 py-0.5
                                   text-[11px] font-medium
                                   {{ $dnsPresentation['statusClasses'] }}"
                        >
                            <span
                                class="size-1.5 rounded-full
                                       {{ $dnsPresentation['dot'] }}"
                            ></span>

                            {{ $dnsPresentation['label'] }}
                        </span>
                    </div>

                    <p
                        class="mt-1.5 max-w-2xl
                               text-sm leading-6
                               text-base-content/55"
                    >
                        {{ $dnsPresentation['description'] }}
                    </p>
                </div>
            </header>

            {{-- DNS details --}}
            <div
                class="grid grid-cols-1
                       border-t border-base-300
                       sm:grid-cols-2"
            >
                {{-- Expected IP --}}
                <div
                    class="flex items-center gap-3
                           border-b border-base-300
                           px-5 py-3.5
                           sm:border-b-0
                           sm:border-l
                           sm:px-6"
                >
                    <div
                        class="flex size-8 shrink-0
                               items-center justify-center
                               rounded-lg
                               bg-base-200/60
                               text-base-content/45"
                    >
                        <x-icon
                            name="lucide.server"
                            class="size-4"
                        />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-[11px]
                                   text-base-content/40"
                        >
                            IP مورد انتظار سرور
                        </p>

                        <p
                            dir="ltr"
                            class="technical-value
                                   mt-0.5 truncate
                                   text-left text-sm
                                   font-medium
                                   text-base-content"
                        >
                            {{ $dnsPreflight['server_ipv4_address'] }}
                        </p>
                    </div>
                </div>

                {{-- Resolved IPv4 --}}
                <div
                    class="flex items-center gap-3
                           px-5 py-3.5
                           sm:px-6"
                >
                    <div
                        @class([
                            'flex size-8 shrink-0',
                            'items-center justify-center',
                            'rounded-lg',

                            'bg-success/10 text-success'
                                => $resolvedIpv4 !== [],

                            'bg-error/10 text-error'
                                => $resolvedIpv4 === [],
                        ])
                    >
                        <x-icon
                            :name="$resolvedIpv4 !== []
                                ? 'lucide.network'
                                : 'lucide.unplug'"
                            class="size-4"
                        />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-[11px]
                                   text-base-content/40"
                        >
                            IPv4 شناسایی‌شده
                        </p>

                        <p
                            dir="ltr"
                            @class([
                                'technical-value mt-0.5 truncate',
                                'text-left text-sm font-medium',

                                'text-base-content'
                                    => $resolvedIpv4 !== [],

                                'text-error'
                                    => $resolvedIpv4 === [],
                            ])
                        >
                            {{ $resolvedIpv4 !== []
                                ? implode(', ', $resolvedIpv4)
                                : 'No A record' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- IPv6 warning --}}
            @if ($resolvedIpv6 !== [])

                <div
                    class="flex items-start gap-3
                           border-t border-warning/15
                           bg-warning/[0.035]
                           px-5 py-4
                           sm:px-6"
                >
                    <div
                        class="flex size-8 shrink-0
                               items-center justify-center
                               rounded-lg
                               bg-warning/10
                               text-warning"
                    >
                        <x-icon
                            name="lucide.triangle-alert"
                            class="size-4"
                        />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-sm font-medium
                                   text-base-content"
                        >
                            رکورد AAAA ناسازگار شناسایی شد
                        </p>

                        <p
                            dir="ltr"
                            class="technical-value
                                   mt-1 break-all
                                   text-left text-xs
                                   leading-6
                                   text-base-content/55"
                        >
                            {{ implode(', ', $resolvedIpv6) }}
                        </p>
                    </div>
                </div>

            @endif

            {{-- Loading server preflight --}}
            @if (
                $ready
                && $serverPreflight === null
            )

                <div
                    class="flex items-center gap-3
                           border-t border-info/15
                           bg-info/[0.035]
                           px-5 py-3.5
                           sm:px-6"
                >
                    <span
                        class="loading loading-spinner
                               loading-xs text-info"
                    ></span>

                    <p
                        class="text-xs leading-6
                               text-base-content/55"
                    >
                        DNS تأیید شد؛ وضعیت پورت‌ها و ساختار
                        سرور در حال بررسی است.
                    </p>
                </div>

            @endif
        </section>

    @endif

    {{-- Server preflight --}}
    @if ($serverPreflight !== null)

        @php
            $serverReady =
                $serverPreflight['ready'];

            $portConflict =
                $serverPreflight['has_port_conflict'];

            $layoutPresentation = match (
                $serverPreflight['layout_state']
            ) {
                'supported' => [
                    'label' => 'پشتیبانی‌شده',
                    'statusClasses' =>
                        'border-success/20 bg-success/10 text-success',
                    'dot' => 'bg-success',
                ],

                'missing' => [
                    'label' => 'نصب استاندارد پیدا نشد',
                    'statusClasses' =>
                        'border-error/20 bg-error/10 text-error',
                    'dot' => 'bg-error',
                ],

                'unreadable' => [
                    'label' => 'قابل خواندن نیست',
                    'statusClasses' =>
                        'border-error/20 bg-error/10 text-error',
                    'dot' => 'bg-error',
                ],

                'invalid_compose' => [
                    'label' => 'Compose نامعتبر',
                    'statusClasses' =>
                        'border-error/20 bg-error/10 text-error',
                    'dot' => 'bg-error',
                ],

                default => [
                    'label' => 'پشتیبانی‌نشده',
                    'statusClasses' =>
                        'border-warning/20 bg-warning/10 text-warning',
                    'dot' => 'bg-warning',
                ],
            };

            $serverPresentation = match (true) {
                $serverReady => [
                    'title' => 'سرور برای HTTPS آماده است',
                    'description' =>
                        'ساختار نصب Marzban تأیید شد و پورت‌های موردنیاز در دسترس هستند.',
                    'icon' => 'lucide.server-plus',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'statusClasses' =>
                        'border-success/20 bg-success/10 text-success',
                    'dot' => 'bg-success',
                    'label' => 'آماده',
                ],

                $portConflict => [
                    'title' => 'تداخل پورت شناسایی شد',
                    'description' =>
                        'یک سرویس خارجی پورت موردنیاز را اشغال کرده است. xDeploy آن سرویس را متوقف یا حذف نمی‌کند.',
                    'icon' => 'lucide.server-off',
                    'iconBackground' => 'bg-error/10',
                    'iconColor' => 'text-error',
                    'statusClasses' =>
                        'border-error/20 bg-error/10 text-error',
                    'dot' => 'bg-error',
                    'label' => 'دارای تداخل',
                ],

                default => [
                    'title' => 'سرور هنوز آماده نیست',
                    'description' =>
                        'ساختار نصب فعلی برای اعمال امن تنظیمات HTTPS قابل استفاده نیست و هیچ فایلی تغییر نکرده است.',
                    'icon' => 'lucide.server-cog',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'statusClasses' =>
                        'border-warning/20 bg-warning/10 text-warning',
                    'dot' => 'bg-warning',
                    'label' => 'نیازمند بررسی',
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
                $portInfo =
                    $serverPreflight['ports'][$port];

                $portPresentations[$port] = match (
                    $portInfo['state']
                ) {
                    'available' => [
                        'label' => 'آزاد',
                        'icon' => 'lucide.circle-check',
                        'iconBackground' => 'bg-success/10',
                        'iconColor' => 'text-success',
                        'statusClasses' =>
                            'border-success/20 bg-success/10 text-success',
                        'dot' => 'bg-success',
                    ],

                    'managed' => [
                        'label' => 'مدیریت‌شده',
                        'icon' => 'lucide.shield-check',
                        'iconBackground' => 'bg-info/10',
                        'iconColor' => 'text-info',
                        'statusClasses' =>
                            'border-info/20 bg-info/10 text-info',
                        'dot' => 'bg-info',
                    ],

                    'conflict' => [
                        'label' => 'دارای تداخل',
                        'icon' => 'lucide.triangle-alert',
                        'iconBackground' => 'bg-error/10',
                        'iconColor' => 'text-error',
                        'statusClasses' =>
                            'border-error/20 bg-error/10 text-error',
                        'dot' => 'bg-error',
                    ],

                    default => [
                        'label' => 'نامشخص',
                        'icon' => 'lucide.circle-help',
                        'iconBackground' => 'bg-warning/10',
                        'iconColor' => 'text-warning',
                        'statusClasses' =>
                            'border-warning/20 bg-warning/10 text-warning',
                        'dot' => 'bg-warning',
                    ],
                };
            }
        @endphp

        <section
            class="overflow-hidden rounded-2xl
                   border border-base-300
                   bg-base-100"
            aria-live="polite"
        >
            {{-- Server status --}}
            <header
                class="flex items-start gap-3.5
                       px-5 py-4
                       sm:px-6"
            >
                <div
                    class="flex size-9 shrink-0
                           items-center justify-center
                           rounded-xl
                           {{ $serverPresentation['iconBackground'] }}
                           {{ $serverPresentation['iconColor'] }}"
                >
                    <x-icon
                        :name="$serverPresentation['icon']"
                        class="size-4"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div
                        class="flex flex-wrap
                               items-center gap-2"
                    >
                        <h4
                            class="text-sm font-semibold
                                   text-base-content"
                        >
                            {{ $serverPresentation['title'] }}
                        </h4>

                        <span
                            class="inline-flex items-center
                                   gap-1.5 rounded-full
                                   border px-2 py-0.5
                                   text-[11px] font-medium
                                   {{ $serverPresentation['statusClasses'] }}"
                        >
                            <span
                                class="size-1.5 rounded-full
                                       {{ $serverPresentation['dot'] }}"
                            ></span>

                            {{ $serverPresentation['label'] }}
                        </span>
                    </div>

                    <p
                        class="mt-1.5 max-w-2xl
                               text-sm leading-6
                               text-base-content/55"
                    >
                        {{ $serverPresentation['description'] }}
                    </p>
                </div>
            </header>

            {{-- Server checks --}}
            <div
                class="grid grid-cols-1
                       border-t border-base-300
                       md:grid-cols-3"
            >
                {{-- Installation layout --}}
                <div
                    class="border-b border-base-300
                           px-5 py-4
                           md:border-b-0
                           md:border-l
                           sm:px-6"
                >
                    <div
                        class="flex items-center
                               gap-3"
                    >
                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg
                                   bg-base-200/60
                                   text-base-content/45"
                        >
                            <x-icon
                                name="lucide.box"
                                class="size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                ساختار نصب
                            </p>

                            <p
                                class="mt-0.5 text-sm
                                       font-medium
                                       text-base-content"
                            >
                                Marzban Compose
                            </p>
                        </div>
                    </div>

                    <span
                        class="mt-3 inline-flex
                               items-center gap-1.5
                               rounded-full
                               border px-2 py-0.5
                               text-[11px] font-medium
                               {{ $layoutPresentation['statusClasses'] }}"
                    >
                        <span
                            class="size-1.5 rounded-full
                                   {{ $layoutPresentation['dot'] }}"
                        ></span>

                        {{ $layoutPresentation['label'] }}
                    </span>
                </div>

                {{-- Ports --}}
                @foreach ([80, 443] as $port)

                    @php
                        $portInfo =
                            $serverPreflight['ports'][$port];

                        $portPresentation =
                            $portPresentations[$port];

                        $ownerLabel =
                            $ownerLabels[$portInfo['owner']]
                            ?? $ownerLabels['unknown'];
                    @endphp

                    <div
                        class="border-b border-base-300
                               px-5 py-4
                               last:border-b-0
                               md:border-b-0
                               md:border-l
                               sm:px-6"
                    >
                        <div
                            class="flex items-center
                                   gap-3"
                        >
                            <div
                                class="flex size-8 shrink-0
                                       items-center justify-center
                                       rounded-lg
                                       {{ $portPresentation['iconBackground'] }}
                                       {{ $portPresentation['iconColor'] }}"
                            >
                                <x-icon
                                    :name="$portPresentation['icon']"
                                    class="size-4"
                                />
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="text-[11px]
                                           text-base-content/40"
                                >
                                    {{ $port === 80
                                        ? 'HTTP'
                                        : 'HTTPS' }}
                                </p>

                                <p
                                    dir="ltr"
                                    class="technical-value
                                           mt-0.5 text-left
                                           text-sm font-medium
                                           text-base-content"
                                >
                                    :{{ $port }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex flex-wrap
                                   items-center gap-2"
                        >
                            <span
                                class="inline-flex items-center
                                       gap-1.5 rounded-full
                                       border px-2 py-0.5
                                       text-[11px] font-medium
                                       {{ $portPresentation['statusClasses'] }}"
                            >
                                <span
                                    class="size-1.5 rounded-full
                                           {{ $portPresentation['dot'] }}"
                                ></span>

                                {{ $portPresentation['label'] }}
                            </span>

                            @if ($portInfo['state'] !== 'available')

                                <span
                                    class="truncate text-xs
                                           text-base-content/45"
                                    title="{{ $ownerLabel }}"
                                >
                                    {{ $ownerLabel }}
                                </span>

                            @endif
                        </div>
                    </div>

                @endforeach
            </div>

            {{-- Activation --}}
            @if ($serverReady)

                <div
                    class="border-t border-base-300
                           bg-primary/[0.025]"
                >
                    <div
                        class="flex flex-col gap-4
                               px-5 py-4
                               sm:flex-row
                               sm:items-center
                               sm:justify-between
                               sm:px-6"
                    >
                        <div
                            class="flex min-w-0
                                   items-start gap-3"
                        >
                            <div
                                class="flex size-9 shrink-0
                                       items-center justify-center
                                       rounded-xl
                                       bg-primary/8
                                       text-primary"
                            >
                                <x-icon
                                    name="lucide.shield-check"
                                    class="size-4"
                                />
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold
                                           text-base-content"
                                >
                                    آماده فعال‌سازی امن HTTPS
                                </p>

                                <p
                                    class="mt-1 max-w-2xl
                                           text-xs leading-6
                                           text-base-content/50"
                                >
                                    xDeploy تنظیمات را اعتبارسنجی می‌کند،
                                    از فایل‌های فعلی نسخه پشتیبان می‌گیرد
                                    و پس از تأیید TLS عملیات را تکمیل می‌کند.
                                </p>

                                <div
                                    class="mt-2 flex items-center
                                           gap-1.5
                                           text-[11px]
                                           text-base-content/40"
                                >
                                    <x-icon
                                        name="lucide.clock-3"
                                        class="size-3.5"
                                    />

                                    دریافت گواهی ممکن است چند دقیقه زمان ببرد.
                                </div>
                            </div>
                        </div>

                        <x-button
                            type="button"
                            label="فعال‌سازی HTTPS"
                            icon="lucide.lock"
                            wire:click="activateHttps"
                            wire:confirm="فعال‌سازی HTTPS فایل‌های مدیریت‌شده Marzban را تغییر می‌دهد و سرویس‌ها را دوباره اجرا می‌کند. ادامه می‌دهید؟"
                            wire:loading.attr="disabled"
                            wire:target="activateHttps"
                            spinner="activateHttps"
                            class="btn-primary btn-sm
                                   shrink-0 rounded-xl"
                        />
                    </div>
                </div>

            @endif
        </section>

    @endif

    {{-- Activation error --}}
    @if ($activationError !== null)

        <div
            role="alert"
            class="flex items-start gap-3
                   rounded-2xl
                   border border-error/20
                   bg-error/5
                   px-5 py-4"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-error/10
                       text-error"
            >
                <x-icon
                    name="lucide.circle-alert"
                    class="size-4"
                />
            </div>

            <div class="min-w-0">
                <p
                    class="text-sm font-semibold
                           text-error"
                >
                    فعال‌سازی HTTPS ناموفق بود
                </p>

                <p
                    class="mt-1 text-sm leading-7
                           text-base-content/60"
                >
                    {{ $activationError }}
                </p>
            </div>
        </div>

    @endif
</div>
