<div class="space-y-6">

    {{-- Page header --}}
    <div
        class="flex flex-col gap-4 sm:flex-row
               sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-3">

            <div
                class="flex size-11 shrink-0 items-center justify-center
                       rounded-2xl border border-base-content/10
                       bg-base-200/60 text-base-content shadow-sm"
            >
                <x-icon
                    name="o-cog-6-tooth"
                    class="size-5"
                />
            </div>

            <div>
                <h2
                    class="text-lg font-bold tracking-tight
                           text-base-content"
                >
                    مدیریت Marzban
                </h2>

                <p
                    class="mt-1 text-sm leading-6
                           text-base-content/55"
                >
                    راه‌اندازی اولیه و تنظیمات اختصاصی پنل
                </p>
            </div>

        </div>

        @unless ($managementUnavailable)
            <x-button
                label="بروزرسانی وضعیت"
                icon="o-arrow-path"
                wire:click="refreshManagement"
                spinner="refreshManagement"
                class="btn-ghost btn-sm"
            />
        @endunless
    </div>

    @if ($managementUnavailable)

        {{-- Management unavailable --}}
        <div
            role="alert"
            class="overflow-hidden rounded-3xl
                   border border-warning/20
                   bg-warning/5 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 p-5 sm:flex-row
                       sm:items-center sm:justify-between sm:p-6"
            >
                <div class="flex items-start gap-4">

                    <div
                        class="flex size-11 shrink-0 items-center
                               justify-center rounded-2xl
                               bg-warning/10 text-warning"
                    >
                        <x-icon
                            name="o-exclamation-triangle"
                            class="size-5"
                        />
                    </div>

                    <div>
                        <h3 class="font-semibold text-base-content">
                            وضعیت مدیریت در دسترس نیست
                        </h3>

                        <p
                            class="mt-1 max-w-xl text-sm leading-7
                                   text-base-content/60"
                        >
                            امکان دریافت اطلاعات Marzban وجود نداشت.
                            اتصال سرور را بررسی کرده و دوباره تلاش کنید.
                        </p>
                    </div>

                </div>

                <x-button
                    label="تلاش دوباره"
                    icon="o-arrow-path"
                    wire:click="refreshManagement"
                    spinner="refreshManagement"
                    class="btn-warning btn-outline btn-sm shrink-0"
                />
            </div>
        </div>

    @else

        {{-- Admin setup --}}
        <livewire:applications.marzban.setup-admin
            :server-id="$serverId"
            :setup-state="$management['setup']['state']"
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

            $httpsPresentation = match ($httpsState) {
                'enabled' => [
                    'label' => 'فعال',
                    'description' => 'دامنه و اتصال امن HTTPS برای پنل Marzban فعال و آماده استفاده است.',
                    'icon' => 'o-lock-closed',
                    'iconBackground' => 'bg-success/10',
                    'iconColor' => 'text-success',
                    'badge' => 'badge-success',
                    'dot' => 'bg-success',
                ],

                'disabled' => [
                    'label' => 'غیرفعال',
                    'description' => 'هنوز دامنه و HTTPS برای پنل Marzban پیکربندی نشده است.',
                    'icon' => 'o-lock-open',
                    'iconBackground' => 'bg-base-200',
                    'iconColor' => 'text-base-content/50',
                    'badge' => 'badge-ghost',
                    'dot' => 'bg-base-content/30',
                ],

                'managed_externally' => [
                    'label' => 'مدیریت خارجی',
                    'description' => 'HTTPS خارج از xDeploy پیکربندی شده و توسط سرویس دیگری مدیریت می‌شود.',
                    'icon' => 'o-arrow-top-right-on-square',
                    'iconBackground' => 'bg-info/10',
                    'iconColor' => 'text-info',
                    'badge' => 'badge-info',
                    'dot' => 'bg-info',
                ],

                'misconfigured' => [
                    'label' => 'نیازمند بررسی',
                    'description' => 'پیکربندی HTTPS شناسایی شد، اما به‌درستی کار نمی‌کند.',
                    'icon' => 'o-exclamation-triangle',
                    'iconBackground' => 'bg-error/10',
                    'iconColor' => 'text-error',
                    'badge' => 'badge-error',
                    'dot' => 'bg-error',
                ],

                default => [
                    'label' => 'نامشخص',
                    'description' => 'وضعیت دامنه و HTTPS در حال حاضر قابل تشخیص نیست.',
                    'icon' => 'o-question-mark-circle',
                    'iconBackground' => 'bg-warning/10',
                    'iconColor' => 'text-warning',
                    'badge' => 'badge-warning',
                    'dot' => 'bg-warning',
                ],
            };

            $dashboardUrl = null;

            if ($httpsState === 'enabled') {
                if (
                    is_string($httpsPanelUrl)
                    && $httpsPanelUrl !== ''
                ) {
                    $dashboardUrl = rtrim($httpsPanelUrl, '/');

                    if (! str_ends_with($dashboardUrl, '/dashboard')) {
                        $dashboardUrl .= '/dashboard';
                    }
                } elseif (
                    is_string($httpsDomain)
                    && $httpsDomain !== ''
                ) {
                    $normalizedDomain = preg_replace(
                        '#^https?://#i',
                        '',
                        trim($httpsDomain),
                    );

                    $dashboardUrl = sprintf(
                        'https://%s/dashboard',
                        trim($normalizedDomain, '/'),
                    );
                }
            }
        @endphp

        {{-- Domain and HTTPS status --}}
        <section
            class="overflow-hidden rounded-3xl
                   border border-base-content/10
                   bg-base-100/80 shadow-sm
                   backdrop-blur-sm"
        >
            {{-- Status content --}}
            <div class="p-5 sm:p-6">

                <div
                    class="flex flex-col gap-5 sm:flex-row
                           sm:items-start sm:justify-between"
                >
                    <div class="flex items-start gap-4">

                        <div
                            @class([
                                'flex size-12 shrink-0 items-center',
                                'justify-center rounded-2xl',
                                'ring-1 ring-inset ring-base-content/5',
                                $httpsPresentation['iconBackground'],
                            ])
                        >
                            <x-icon
                                :name="$httpsPresentation['icon']"
                                @class([
                                    'size-5',
                                    $httpsPresentation['iconColor'],
                                ])
                            />
                        </div>

                        <div class="min-w-0">

                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <h3
                                    class="font-semibold
                                           text-base-content"
                                >
                                    دامنه و HTTPS
                                </h3>

                                <span
                                    @class([
                                        'badge badge-sm gap-1.5',
                                        'border-0 font-medium',
                                        $httpsPresentation['badge'],
                                    ])
                                >
                                    <span
                                        @class([
                                            'size-1.5 rounded-full',
                                            $httpsPresentation['dot'],
                                        ])
                                    ></span>

                                    {{ $httpsPresentation['label'] }}
                                </span>
                            </div>

                            <p
                                class="mt-2 max-w-2xl text-sm
                                       leading-7 text-base-content/60"
                            >
                                {{ $httpsPresentation['description'] }}
                            </p>

                        </div>

                    </div>
                </div>

            </div>

            @if ($dashboardUrl !== null)

                {{-- Dashboard action --}}
                <div
                    class="border-t border-base-content/10
                           bg-base-200/25 p-4 sm:p-5"
                >
                    <a
                        href="{{ $dashboardUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group flex items-center justify-between
                               gap-4 rounded-2xl border
                               border-primary/15 bg-base-100
                               p-4 shadow-sm outline-none
                               transition-all duration-200
                               hover:-translate-y-0.5
                               hover:border-primary/30
                               hover:shadow-md
                               focus-visible:ring-2
                               focus-visible:ring-primary
                               focus-visible:ring-offset-2
                               focus-visible:ring-offset-base-100"
                    >
                        <div class="flex min-w-0 items-center gap-3">

                            <div
                                class="flex size-10 shrink-0 items-center
                                       justify-center rounded-xl
                                       bg-primary/10 text-primary
                                       transition-transform duration-200
                                       group-hover:scale-105"
                            >
                                <x-icon
                                    name="o-window"
                                    class="size-5"
                                />
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold
                                           text-base-content"
                                >
                                    ورود به داشبورد Marzban
                                </p>

                                <p
                                    class="mt-1 text-xs
                                           text-base-content/50"
                                >
                                    پنل مدیریت را در صفحه جدید باز کنید
                                </p>
                            </div>

                        </div>

                        <div
                            class="flex size-9 shrink-0 items-center
                                   justify-center rounded-xl
                                   text-primary transition-colors
                                   duration-200
                                   group-hover:bg-primary/10"
                        >
                            <x-icon
                                name="o-arrow-top-right-on-square"
                                class="size-5 transition-transform
                                       duration-200
                                       group-hover:-translate-y-0.5
                                       group-hover:translate-x-0.5"
                            />
                        </div>
                    </a>
                </div>

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
