<div class="space-y-4">

    {{-- Management header --}}
    <header
        class="flex items-center
               justify-between gap-4
               px-1"
    >
        <div
            class="flex min-w-0
                   items-center gap-3"
        >
            <div
                class="flex size-9 shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-base-200/70
                       text-base-content/55"
            >
                <x-icon
                    name="lucide.settings-2"
                    class="size-4"
                />
            </div>

            <div class="min-w-0">
                <h2
                    class="text-base font-semibold
                           text-base-content"
                >
                    مدیریت Marzban
                </h2>

                <p
                    class="mt-0.5 text-sm
                           text-base-content/50"
                >
                    تنظیمات و قابلیت‌های اختصاصی پنل
                </p>
            </div>
        </div>

        @unless ($managementUnavailable)

            <div
                class="tooltip tooltip-bottom
                       before:z-50
                       before:whitespace-nowrap
                       before:text-xs
                       after:z-50"
                data-tip="بروزرسانی وضعیت مدیریت"
            >
                <button
                    type="button"
                    wire:click="refreshManagement"
                    wire:loading.attr="disabled"
                    wire:target="refreshManagement"
                    aria-label="بروزرسانی وضعیت مدیریت Marzban"
                    class="flex size-9 shrink-0
                           items-center justify-center
                           rounded-xl
                           border border-transparent
                           text-base-content/45
                           transition-colors duration-150
                           hover:border-base-300
                           hover:bg-base-200/60
                           hover:text-primary
                           disabled:pointer-events-none
                           disabled:opacity-50"
                >
                    <span
                        wire:loading.remove
                        wire:target="refreshManagement"
                    >
                        <x-icon
                            name="lucide.refresh-cw"
                            class="size-4"
                        />
                    </span>

                    <span
                        wire:loading
                        wire:target="refreshManagement"
                        class="loading loading-spinner
                               loading-xs"
                    ></span>
                </button>
            </div>

        @endunless
    </header>

    @if ($managementUnavailable)

        {{-- Management unavailable --}}
        <section
            role="alert"
            class="rounded-2xl
                   border border-warning/20
                   bg-warning/5"
        >
            <div
                class="flex flex-col gap-4
                       px-5 py-5
                       sm:flex-row
                       sm:items-center
                       sm:justify-between
                       sm:px-6"
            >
                <div
                    class="flex items-start gap-3.5"
                >
                    <div
                        class="flex size-10 shrink-0
                               items-center justify-center
                               rounded-xl
                               bg-warning/10
                               text-warning"
                    >
                        <x-icon
                            name="lucide.triangle-alert"
                            class="size-4.5"
                        />
                    </div>

                    <div>
                        <h3
                            class="text-sm font-semibold
                                   text-base-content"
                        >
                            وضعیت مدیریت در دسترس نیست
                        </h3>

                        <p
                            class="mt-1 max-w-xl
                                   text-sm leading-7
                                   text-base-content/55"
                        >
                            امکان دریافت اطلاعات مدیریتی Marzban
                            وجود نداشت. اتصال سرور را بررسی کرده
                            و دوباره تلاش کنید.
                        </p>
                    </div>
                </div>

                <x-button
                    label="تلاش دوباره"
                    icon="lucide.refresh-cw"
                    wire:click="refreshManagement"
                    wire:loading.attr="disabled"
                    wire:target="refreshManagement"
                    spinner="refreshManagement"
                    class="btn-warning btn-outline
                           btn-sm shrink-0 rounded-xl"
                />
            </div>
        </section>

    @else

        {{-- Admin setup / detected admins --}}
        <livewire:applications.marzban.setup-admin
            :server-id="$serverId"
            :setup-state="$management['setup']['state']"
            :admins="$management['setup']['admins'] ?? []"
            :key="'marzban-setup-admin-'.$serverId"
        />

        @php
            $httpsState = data_get(
                $management,
                'https.state',
                'unknown',
            );

            $httpsDomain = data_get(
                $management,
                'https.domain',
            );

            $httpsPanelUrl = data_get(
                $management,
                'https.panel_url',
            );

            $httpsDomain = is_string($httpsDomain)
                && trim($httpsDomain) !== ''
                    ? trim($httpsDomain)
                    : null;

            $httpsPanelUrl = is_string($httpsPanelUrl)
                && trim($httpsPanelUrl) !== ''
                    ? trim($httpsPanelUrl)
                    : null;

            $httpsPresentation = match ($httpsState) {
                'enabled' => [
                    'label' => 'فعال',
                    'description' =>
                        'دامنه و اتصال امن HTTPS برای پنل Marzban فعال و آماده استفاده است.',
                    'icon' => 'lucide.lock',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'statusClasses' =>
                        'border-success/20 bg-success/10 text-success',
                    'dot' => 'bg-success',
                ],

                'disabled' => [
                    'label' => 'غیرفعال',
                    'description' =>
                        'هنوز دامنه و HTTPS برای پنل Marzban پیکربندی نشده است.',
                    'icon' => 'lucide.unlock',
                    'iconBackground' => 'bg-base-200/70',
                    'iconColor' => 'text-base-content/45',
                    'statusClasses' =>
                        'border-base-300 bg-base-200/70 text-base-content/55',
                    'dot' => 'bg-base-content/30',
                ],

                'managed_externally' => [
                    'label' => 'مدیریت خارجی',
                    'description' =>
                        'HTTPS خارج از xDeploy پیکربندی شده و توسط سرویس دیگری مدیریت می‌شود.',
                    'icon' => 'lucide.external-link',
                    'iconBackground' => 'bg-info/10',
                    'iconColor' => 'text-info',
                    'statusClasses' =>
                        'border-info/20 bg-info/10 text-info',
                    'dot' => 'bg-info',
                ],

                'misconfigured' => [
                    'label' => 'نیازمند بررسی',
                    'description' =>
                        'پیکربندی HTTPS شناسایی شده، اما به‌درستی کار نمی‌کند.',
                    'icon' => 'lucide.triangle-alert',
                    'iconBackground' => 'bg-error/10',
                    'iconColor' => 'text-error',
                    'statusClasses' =>
                        'border-error/20 bg-error/10 text-error',
                    'dot' => 'bg-error',
                ],

                default => [
                    'label' => 'نامشخص',
                    'description' =>
                        'وضعیت دامنه و HTTPS در حال حاضر قابل تشخیص نیست.',
                    'icon' => 'lucide.circle-help',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'statusClasses' =>
                        'border-warning/20 bg-warning/10 text-warning',
                    'dot' => 'bg-warning',
                ],
            };

            $dashboardUrl = null;

            if ($httpsState === 'enabled') {
                if ($httpsPanelUrl !== null) {
                    $dashboardUrl = rtrim(
                        $httpsPanelUrl,
                        '/',
                    );

                    if (
                        ! str_ends_with(
                            $dashboardUrl,
                            '/dashboard',
                        )
                    ) {
                        $dashboardUrl .= '/dashboard';
                    }
                } elseif ($httpsDomain !== null) {
                    $normalizedDomain = preg_replace(
                        '#^https?://#i',
                        '',
                        $httpsDomain,
                    );

                    if (
                        is_string($normalizedDomain)
                        && trim($normalizedDomain, '/') !== ''
                    ) {
                        $dashboardUrl = sprintf(
                            'https://%s/dashboard',
                            trim(
                                $normalizedDomain,
                                '/',
                            ),
                        );
                    }
                }
            }
        @endphp

        {{-- Domain and HTTPS --}}
        <section
            class="overflow-hidden rounded-2xl
                   border border-base-300
                   bg-base-100"
        >
            <div
                class="flex flex-col gap-4
                       px-5 py-5
                       sm:flex-row
                       sm:items-start
                       sm:justify-between
                       sm:px-6"
            >
                <div
                    class="flex min-w-0
                           items-start gap-3.5"
                >
                    <div
                        @class([
                            'flex size-10 shrink-0',
                            'items-center justify-center',
                            'rounded-xl',
                            $httpsPresentation['iconBackground'],
                        ])
                    >
                        <x-icon
                            :name="$httpsPresentation['icon']"
                            @class([
                                'size-4.5',
                                $httpsPresentation['iconColor'],
                            ])
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
                                دامنه و HTTPS
                            </h3>

                            <span
                                class="inline-flex items-center
                                       gap-1.5 rounded-full
                                       border px-2 py-0.5
                                       text-[11px] font-medium
                                       {{ $httpsPresentation['statusClasses'] }}"
                            >
                                <span
                                    class="size-1.5 rounded-full
                                           {{ $httpsPresentation['dot'] }}"
                                ></span>

                                {{ $httpsPresentation['label'] }}
                            </span>
                        </div>

                        <p
                            class="mt-1.5 max-w-2xl
                                   text-sm leading-7
                                   text-base-content/55"
                        >
                            {{ $httpsPresentation['description'] }}
                        </p>

                        @if ($httpsDomain !== null)

                            <div
                                class="mt-3 inline-flex
                                       items-center gap-2
                                       rounded-lg
                                       bg-base-200/60
                                       px-2.5 py-1.5"
                            >
                                <x-icon
                                    name="lucide.globe"
                                    class="size-3.5
                                           text-base-content/40"
                                />

                                <span
                                    dir="ltr"
                                    class="technical-value
                                           text-xs
                                           text-base-content/60"
                                >
                                    {{ $httpsDomain }}
                                </span>
                            </div>

                        @endif
                    </div>
                </div>
            </div>

            @if ($dashboardUrl !== null)

                <a
                    href="{{ $dashboardUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group/action
       flex items-center
       justify-between gap-4
       border-t border-success/15
       bg-success/8
       px-5 py-3.5
       transition-colors duration-150
       hover:bg-success/10
       sm:px-6"
                >
                    <div
                        class="flex min-w-0
                   items-center gap-2.5"
                    >
                        <div
                            class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-primary/8
                       text-primary"
                        >
                            <x-icon
                                name="lucide.panel-top"
                                class="size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-sm font-semibold
                           text-base-content/80
                           transition-colors
                           group-hover/action:text-primary"
                            >
                                ورود به پنل Marzban
                            </p>

                            <p
                                class="mt-0.5 text-[11px]
                           text-base-content/40"
                            >
                                داشبورد مدیریت در صفحه جدید باز می‌شود
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex size-8 shrink-0
                   items-center justify-center
                   rounded-lg
                   border border-base-300
                   bg-base-100
                   text-base-content/40
                   transition-all duration-150
                   group-hover/action:border-primary/20
                   group-hover/action:bg-primary
                   group-hover/action:text-primary-content"
                    >
                        <x-icon
                            name="lucide.external-link"
                            class="size-3.5"
                        />
                    </div>
                </a>

            @endif
        </section>

        @if ($httpsState === 'disabled')

            <livewire:applications.marzban.setup-https
                :server-id="$serverId"
                :key="'marzban-setup-https-'.$serverId"
            />

        @endif

    @endif
</div>
