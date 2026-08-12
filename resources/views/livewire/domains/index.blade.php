<x-servers.workspace
    :server="$server"
    wire:key="server-domains-workspace-{{ $server->getKey() }}"
>
    <div
        wire:init="loadDomains"
        class="space-y-5"
    >
        @php
            $applicationInstalled = data_get(
                $management,
                'application.is_installed',
                false,
            ) === true;

            $applicationRunning = data_get(
                $management,
                'application.is_running',
                false,
            ) === true;
        @endphp

        <header
            class="flex flex-col gap-4 px-1 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <x-icon
                        name="lucide.globe-2"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <h1
                        class="text-xl font-semibold tracking-tight text-base-content sm:text-2xl"
                    >
                        دامنه‌ها و HTTPS
                    </h1>

                    <p
                        class="mt-1.5 max-w-2xl text-sm leading-7 text-base-content/50"
                    >
                        دامنه‌ها را به برنامه‌های این سرور متصل کنید و وضعیت DNS و HTTPS را یک‌جا مدیریت کنید.
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2 self-start">
                @if ($loaded && ! $unavailable)
                    <x-button
                        icon="lucide.refresh-cw"
                        wire:click="refreshDomains"
                        spinner="refreshDomains"
                        wire:loading.attr="disabled"
                        wire:target="refreshDomains"
                        aria-label="بروزرسانی وضعیت دامنه‌ها"
                        class="btn-ghost btn-sm btn-square rounded-xl"
                    />

                    <x-button
                        label="افزودن دامنه"
                        icon="lucide.plus"
                        wire:click="openDomainDrawer"
                        :disabled="! $canAddDomain"
                        class="btn-primary btn-sm rounded-xl"
                    />
                @endif
            </div>
        </header>

        @if ($endpointError !== null)
            <div
                role="alert"
                class="flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-5 py-4"
            >
                <x-icon
                    name="lucide.triangle-alert"
                    class="mt-0.5 !size-4 shrink-0 text-warning"
                />

                <p class="text-sm leading-7 text-base-content/60">
                    {{ $endpointError }}
                </p>
            </div>
        @endif

        @if (! $loaded)
            <section
                class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6"
                aria-live="polite"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="loading loading-spinner loading-sm text-primary"
                    ></span>

                    <div>
                        <h2 class="text-sm font-semibold text-base-content">
                            در حال دریافت وضعیت دامنه‌ها
                        </h2>

                        <p class="mt-1 text-xs leading-6 text-base-content/45">
                            اتصال برنامه‌ها و وضعیت HTTPS از سرور بررسی می‌شود.
                        </p>
                    </div>
                </div>
            </section>
        @elseif ($unavailable)
            <section
                role="alert"
                class="rounded-2xl border border-warning/20 bg-warning/5 p-5 sm:p-6"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3.5">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                        >
                            <x-icon
                                name="lucide.cloud-off"
                                class="!size-4.5"
                            />
                        </div>

                        <div>
                            <h2 class="text-sm font-semibold text-base-content">
                                وضعیت دامنه‌ها در دسترس نیست
                            </h2>

                            <p
                                class="mt-1 max-w-xl text-sm leading-7 text-base-content/55"
                            >
                                ارتباط با سرور کامل نشد. اتصال SSH را بررسی کرده و دوباره تلاش کنید.
                            </p>
                        </div>
                    </div>

                    <x-button
                        label="تلاش دوباره"
                        icon="lucide.refresh-cw"
                        wire:click="refreshDomains"
                        spinner="refreshDomains"
                        class="btn-warning btn-outline btn-sm shrink-0 rounded-xl"
                    />
                </div>
            </section>
        @else
            @if ($endpoints === [])
                <section
                    class="rounded-2xl border border-dashed border-base-300 bg-base-100 px-5 py-12 text-center sm:px-6"
                >
                    <div
                        class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary/8 text-primary"
                    >
                        <x-icon
                            name="lucide.globe-2"
                            class="!size-5"
                        />
                    </div>

                    @if (! $applicationInstalled)
                        <h2 class="mt-4 text-sm font-semibold text-base-content">
                            هنوز برنامه‌ای برای اتصال دامنه آماده نیست
                        </h2>

                        <p
                            class="mx-auto mt-1.5 max-w-lg text-sm leading-7 text-base-content/45"
                        >
                            ابتدا یکی از برنامه‌های پشتیبانی‌شده را نصب کنید. دامنه فقط به برنامه‌ای تخصیص داده می‌شود که روی همین سرور آماده باشد.
                        </p>

                        <x-button
                            label="مشاهده برنامه‌ها"
                            icon="lucide.package-open"
                            :link="route('panel.servers.applications.index', ['server' => $server])"
                            wire:navigate
                            class="btn-primary btn-sm mt-5 rounded-xl"
                        />
                    @else
                        <h2 class="mt-4 text-sm font-semibold text-base-content">
                            هنوز دامنه‌ای به برنامه‌ها متصل نشده است
                        </h2>

                        <p
                            class="mx-auto mt-1.5 max-w-lg text-sm leading-7 text-base-content/45"
                        >
                            برنامه مقصد را انتخاب کنید، دامنه را وارد کنید و پس از تنظیم DNS، HTTPS را فعال کنید.
                        </p>

                        <x-button
                            label="افزودن دامنه"
                            icon="lucide.plus"
                            wire:click="openDomainDrawer"
                            class="btn-primary btn-sm mt-5 rounded-xl"
                        />
                    @endif
                </section>
            @else
                <div class="grid grid-cols-1 gap-4">
                    @foreach ($endpoints as $endpoint)
                        <x-domains.domain-card
                            wire:key="public-endpoint-{{ $endpoint['id'] }}"
                            :domain="$endpoint['domain']"
                            :application="$endpoint['application_name']"
                            :state="$endpoint['state']"
                            :open-url="$endpoint['open_url']"
                            :application-url="$endpoint['application_url']"
                            :manage-endpoint-id="$endpoint['id']"
                        />
                    @endforeach
                </div>

                @unless ($applicationRunning)
                    <div
                        role="status"
                        class="flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-5 py-4"
                    >
                        <x-icon
                            name="lucide.triangle-alert"
                            class="mt-0.5 !size-4 shrink-0 text-warning"
                        />

                        <p class="text-sm leading-7 text-base-content/55">
                            Marzban در حال اجرا نیست. دامنه ثبت شده باقی می‌ماند، اما دسترسی عمومی تا اجرای برنامه در دسترس نخواهد بود.
                        </p>
                    </div>
                @endunless

                @if (! $canAddDomain)
                    <p class="px-1 text-xs leading-6 text-base-content/40">
                        هر برنامه در نسخه فعلی xDeploy می‌تواند یک دامنه عمومی داشته باشد. برای افزودن دامنه جدید، برنامه دیگری باید از اتصال دامنه پشتیبانی کند.
                    </p>
                @endif
            @endif
        @endif

        @if ($loaded && ! $unavailable)
            <x-drawer
                wire:model="showDrawer"
                title="مدیریت دامنه"
                subtitle="اتصال دامنه به یک برنامه و فعال‌سازی HTTPS"
                separator
                with-close-button
                close-on-escape
                right
                class="w-11/12 sm:w-[36rem]"
            >
                <div class="space-y-5 pb-4">
                    @if ($endpointError !== null)
                        <div
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border border-error/20 bg-error/5 px-4 py-3"
                        >
                            <x-icon
                                name="lucide.circle-alert"
                                class="mt-0.5 !size-4 shrink-0 text-error"
                            />

                            <p class="text-sm leading-7 text-base-content/60">
                                {{ $endpointError }}
                            </p>
                        </div>
                    @endif

                    @if ($selectedEndpoint?->isActive())
                        <section
                            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
                        >
                            <div class="p-5">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success"
                                    >
                                        <x-icon
                                            name="lucide.globe-lock"
                                            class="!size-4"
                                        />
                                    </span>

                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-base-content">
                                            دامنه فعال است
                                        </h3>

                                        <p
                                            dir="ltr"
                                            class="technical-value mt-1 break-all text-left text-sm text-base-content/70"
                                        >
                                            {{ $selectedEndpoint->domain }}
                                        </p>

                                        <p class="mt-3 text-sm leading-7 text-base-content/50">
                                            این دامنه به {{ $selectedEndpoint->application_type === \App\Domain\Application\Shared\Enums\ApplicationType::Marzban ? 'Marzban' : $selectedEndpoint->application_type->value }} متصل است و HTTPS فعال شده است.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="border-t border-base-300 bg-base-200/20 px-5 py-3.5 text-xs leading-6 text-base-content/45"
                            >
                                تغییر یا حذف دامنه فعال پس از اضافه‌شدن lifecycle امن Disable HTTPS ارائه می‌شود؛ حذف صرفاً رکورد دیتابیس انجام نمی‌شود.
                            </div>
                        </section>
                    @elseif ($showSetup && $selectedApplication === 'marzban')
                        <div
                            class="flex items-center gap-3 rounded-xl border border-primary/15 bg-primary/[0.04] px-4 py-3"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <x-icon
                                    name="lucide.box"
                                    class="!size-4"
                                />
                            </span>

                            <div>
                                <div class="text-xs text-base-content/40">
                                    برنامه مقصد
                                </div>

                                <div class="mt-0.5 text-sm font-medium text-base-content">
                                    Marzban
                                </div>
                            </div>
                        </div>

                        <livewire:applications.marzban.setup-https
                            :server-id="$serverId"
                            :endpoint-id="$selectedEndpoint?->getKey()"
                            :key="'domains-endpoint-setup-'.$serverId.'-marzban'"
                        />

                        @if ($selectedEndpoint !== null && ! $selectedEndpoint->isActive())
                            <div
                                class="flex items-center justify-between gap-4 border-t border-base-300 pt-4"
                            >
                                <p class="text-xs leading-6 text-base-content/40">
                                    لغو اتصال فقط تا قبل از فعال‌شدن HTTPS امکان‌پذیر است.
                                </p>

                                <x-button
                                    label="لغو اتصال"
                                    icon="lucide.unlink"
                                    wire:click="cancelPendingEndpoint({{ $selectedEndpoint->getKey() }})"
                                    wire:confirm="اتصال این دامنه به Marzban لغو شود؟"
                                    class="btn-error btn-outline btn-sm shrink-0 rounded-xl"
                                />
                            </div>
                        @endif
                    @else
                        <section>
                            <div class="mb-3">
                                <h3 class="text-sm font-semibold text-base-content">
                                    برنامه مقصد را انتخاب کنید
                                </h3>

                                <p class="mt-1 text-xs leading-6 text-base-content/45">
                                    دامنه به برنامه انتخاب‌شده روی همین سرور تخصیص داده می‌شود.
                                </p>
                            </div>

                            <div class="space-y-2.5">
                                @forelse ($availableApplications as $application)
                                    <button
                                        type="button"
                                        wire:click="selectApplication('{{ $application['type'] }}')"
                                        @class([
                                            'flex w-full items-center gap-3 rounded-2xl border p-4 text-right transition',
                                            'border-primary/30 bg-primary/[0.05] ring-1 ring-primary/10'
                                                => $selectedApplication === $application['type'],
                                            'border-base-300 bg-base-100 hover:border-primary/20 hover:bg-primary/[0.025]'
                                                => $selectedApplication !== $application['type'],
                                        ])
                                    >
                                        <span
                                            @class([
                                                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                                'bg-primary/10 text-primary'
                                                    => $selectedApplication === $application['type'],
                                                'bg-base-200/70 text-base-content/45'
                                                    => $selectedApplication !== $application['type'],
                                            ])
                                        >
                                            <x-icon
                                                :name="$application['icon']"
                                                class="!size-4.5"
                                            />
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-base-content">
                                                {{ $application['name'] }}
                                            </span>

                                            <span class="mt-1 block text-xs leading-6 text-base-content/45">
                                                {{ $application['description'] }}
                                            </span>
                                        </span>

                                        <span
                                            @class([
                                                'flex size-5 shrink-0 items-center justify-center rounded-full border',
                                                'border-primary bg-primary text-primary-content'
                                                    => $selectedApplication === $application['type'],
                                                'border-base-300'
                                                    => $selectedApplication !== $application['type'],
                                            ])
                                        >
                                            @if ($selectedApplication === $application['type'])
                                                <x-icon
                                                    name="lucide.check"
                                                    class="!size-3"
                                                />
                                            @endif
                                        </span>
                                    </button>
                                @empty
                                    <div
                                        class="rounded-2xl border border-dashed border-base-300 bg-base-100 px-5 py-8 text-center"
                                    >
                                        <x-icon
                                            name="lucide.package-check"
                                            class="mx-auto !size-5 text-base-content/30"
                                        />

                                        <p class="mt-3 text-sm font-medium text-base-content">
                                            برنامه آماده دیگری وجود ندارد
                                        </p>

                                        <p class="mt-1 text-xs leading-6 text-base-content/45">
                                            هر برنامه فعلاً فقط یک دامنه عمومی می‌تواند داشته باشد.
                                        </p>
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        @if ($availableApplications !== [])
                            <div class="flex justify-end border-t border-base-300 pt-4">
                                <x-button
                                    label="ادامه"
                                    icon="lucide.arrow-left"
                                    wire:click="continueDomainSetup"
                                    :disabled="$selectedApplication === null"
                                    class="btn-primary btn-sm rounded-xl"
                                />
                            </div>
                        @endif
                    @endif
                </div>
            </x-drawer>
        @endif
    </div>
</x-servers.workspace>
