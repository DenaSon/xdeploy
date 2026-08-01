<div class="space-y-6">

    <div
        class="flex flex-col gap-2
               sm:flex-row sm:items-start sm:justify-between"
    >
        <div>
            <h2 class="text-lg font-bold text-base-content">
                مدیریت Marzban
            </h2>

            <p class="mt-1 text-sm leading-7 text-base-content/60">
                راه‌اندازی اولیه و ابزارهای اختصاصی Marzban را از این بخش
                مدیریت کنید.
            </p>
        </div>
    </div>

    @if ($managementUnavailable)

        <x-card
            class="border border-warning/20 bg-warning/5 shadow-sm"
        >
            <div
                class="flex flex-col gap-5
                       sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-4">

                    <div
                        class="flex size-11 shrink-0 items-center
                               justify-center rounded-2xl bg-warning/10"
                    >
                        <x-icon
                            name="o-exclamation-triangle"
                            class="size-5 text-warning"
                        />
                    </div>

                    <div>
                        <h3 class="font-semibold text-base-content">
                            وضعیت مدیریت Marzban در دسترس نیست
                        </h3>

                        <p
                            class="mt-1 text-sm leading-7
                                   text-base-content/60"
                        >
                            امکان دریافت وضعیت راه‌اندازی Marzban وجود
                            نداشت. پس از بررسی اتصال دوباره تلاش کنید.
                        </p>
                    </div>

                </div>

                <x-button
                    label="تلاش دوباره"
                    icon="o-arrow-path"
                    wire:click="refreshManagement"
                    spinner="refreshManagement"
                    class="btn-warning btn-outline"
                />
            </div>
        </x-card>

    @else

        <livewire:applications.marzban.setup-admin
            :server-id="$serverId"
            :setup-state="$management['setup']['state']"
            :key="'marzban-setup-admin-'.$serverId"
        />

    @endif

</div>
