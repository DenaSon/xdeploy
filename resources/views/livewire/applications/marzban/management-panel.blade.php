<div class="space-y-4">

    {{-- Management header --}}
    <header
        class="flex items-center justify-between gap-4 px-1"
    >
        <div class="flex min-w-0 items-center gap-3">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200/70 text-base-content/55"
            >
                <x-icon
                    name="lucide.settings-2"
                    class="size-4"
                />
            </div>

            <div class="min-w-0">
                <h2 class="text-base font-semibold text-base-content">
                    مدیریت Marzban
                </h2>

                <p class="mt-0.5 text-sm text-base-content/50">
                    تنظیمات و قابلیت‌های اختصاصی پنل
                </p>
            </div>
        </div>

        @unless ($managementUnavailable)
            <div
                class="tooltip tooltip-bottom before:z-50 before:whitespace-nowrap before:text-xs after:z-50"
                data-tip="بروزرسانی وضعیت مدیریت"
            >
                <button
                    type="button"
                    wire:click="refreshManagement"
                    wire:loading.attr="disabled"
                    wire:target="refreshManagement"
                    aria-label="بروزرسانی وضعیت مدیریت Marzban"
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-transparent text-base-content/45 transition-colors duration-150 hover:border-base-300 hover:bg-base-200/60 hover:text-primary disabled:pointer-events-none disabled:opacity-50"
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
                        class="loading loading-spinner loading-xs"
                    ></span>
                </button>
            </div>
        @endunless
    </header>

    @if ($managementUnavailable)
        <section
            role="alert"
            class="rounded-2xl border border-warning/20 bg-warning/5"
        >
            <div
                class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <x-icon
                            name="lucide.triangle-alert"
                            class="size-4.5"
                        />
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-base-content">
                            وضعیت مدیریت در دسترس نیست
                        </h3>

                        <p
                            class="mt-1 max-w-xl text-sm leading-7 text-base-content/55"
                        >
                            امکان دریافت اطلاعات مدیریتی Marzban وجود نداشت. اتصال سرور را بررسی کرده و دوباره تلاش کنید.
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
                    class="btn-warning btn-outline btn-sm shrink-0 rounded-xl"
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
        @endphp

        {{-- Domain management lives in the server domains workspace. --}}
        <x-domains.application-summary
            :server="$serverId"
            :state="$httpsState"
            :domain="$httpsDomain"
        />
    @endif
</div>
