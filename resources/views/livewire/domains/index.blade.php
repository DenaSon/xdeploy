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

            $httpsState = data_get(
                $management,
                'https.state',
                'unknown',
            );

            $domain = data_get(
                $management,
                'https.domain',
            );

            $domain = is_string($domain) && trim($domain) !== ''
                ? trim($domain)
                : null;

            $canAddDomain = $loaded
                && ! $unavailable
                && $applicationInstalled
                && $httpsState === 'disabled';

            $applicationUrl = route(
                'panel.servers.applications.show',
                [
                    'server' => $server,
                    'application' => 'marzban',
                ],
            );

            $openUrl = $httpsState === 'enabled'
                && $domain !== null
                    ? 'https://'.preg_replace('#^https?://#i', '', $domain).'/dashboard/'
                    : null;
        @endphp

        {{-- Page heading --}}
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
                        دامنه‌های متصل به برنامه‌های این سرور و وضعیت اتصال امن آن‌ها را یک‌جا مدیریت کنید.
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
                @endif

                @if ($canAddDomain)
                    <x-button
                        label="افزودن دامنه"
                        icon="lucide.plus"
                        wire:click="openDomainDrawer"
                        class="btn-primary btn-sm rounded-xl"
                    />
                @endif
            </div>
        </header>

        {{-- Initial loading --}}
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
                            وضعیت Marzban و اتصال HTTPS از سرور بررسی می‌شود.
                        </p>
                    </div>
                </div>
            </section>

        {{-- Load failure --}}
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
                                ارتباط با سرور یا دریافت وضعیت Marzban کامل نشد. اتصال SSH را بررسی کرده و دوباره تلاش کنید.
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

        {{-- Marzban is not installed --}}
        @elseif (! $applicationInstalled)
            <section
                class="rounded-2xl border border-dashed border-base-300 bg-base-100 px-5 py-12 text-center sm:px-6"
            >
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-base-200/70 text-base-content/40"
                >
                    <x-icon
                        name="lucide.globe-plus"
                        class="!size-5"
                    />
                </div>

                <h2 class="mt-4 text-sm font-semibold text-base-content">
                    هنوز برنامه‌ای برای اتصال دامنه آماده نیست
                </h2>

                <p
                    class="mx-auto mt-1.5 max-w-lg text-sm leading-7 text-base-content/45"
                >
                    ابتدا یکی از برنامه‌های پشتیبانی‌شده را روی این سرور نصب کنید. در حال حاضر اتصال دامنه برای Marzban در دسترس است.
                </p>

                <x-button
                    label="مشاهده برنامه‌ها"
                    icon="lucide.package-open"
                    :link="route('panel.servers.applications.index', ['server' => $server])"
                    wire:navigate
                    class="btn-primary btn-sm mt-5 rounded-xl"
                />
            </section>

        {{-- No domain yet --}}
        @elseif ($httpsState === 'disabled')
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

                <h2 class="mt-4 text-sm font-semibold text-base-content">
                    هنوز دامنه‌ای متصل نیست
                </h2>

                <p
                    class="mx-auto mt-1.5 max-w-lg text-sm leading-7 text-base-content/45"
                >
                    یک دامنه یا زیردامنه را به Marzban متصل کنید. xDeploy ابتدا DNS و وضعیت سرور را بررسی می‌کند و سپس HTTPS را فعال می‌کند.
                </p>

                <div class="mt-5 flex justify-center">
                    <x-button
                        label="افزودن دامنه"
                        icon="lucide.plus"
                        wire:click="openDomainDrawer"
                        class="btn-primary btn-sm rounded-xl"
                    />
                </div>
            </section>

        {{-- Existing domain --}}
        @else
            <x-domains.domain-card
                :domain="$domain"
                application="Marzban"
                :state="$httpsState"
                :open-url="$openUrl"
                :application-url="$applicationUrl"
            />

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
                        دامنه ثبت شده است، اما Marzban در حال اجرا نیست. برای دسترسی عمومی ابتدا وضعیت برنامه را بررسی کنید.
                    </p>
                </div>
            @endunless
        @endif

        @if ($canAddDomain)
            <x-drawer
                wire:model="showDrawer"
                title="افزودن دامنه"
                subtitle="اتصال دامنه به Marzban و فعال‌سازی HTTPS"
                separator
                with-close-button
                close-on-escape
                right
                class="w-11/12 sm:w-[34rem]"
            >
                <div class="pb-4">
                    <livewire:applications.marzban.setup-https
                        :server-id="$serverId"
                        :key="'domains-marzban-https-'.$serverId"
                    />
                </div>
            </x-drawer>
        @endif
    </div>
</x-servers.workspace>
