<div
    class="relative isolate mx-auto w-full max-w-[1600px]"
    @if (
        ! $sshUnavailable
        && $readinessIssue === null
        && $errorMessage === null
    )
        wire:poll.visible.30s="checkConnection"
    @endif
>
    {{-- Ambient background --}}
    <div
        class="pointer-events-none absolute -top-40 -left-40 -z-10
               size-[28rem] rounded-full bg-primary/10 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute -right-32 bottom-0 -z-10
               size-96 rounded-full bg-secondary/8 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute top-1/3 left-1/2 -z-10
               size-80 -translate-x-1/2 rounded-full
               bg-accent/5 blur-3xl"
    ></div>

    @if ($sshUnavailable)

        {{-- Persistent SSH unavailable state --}}
        <div
            wire:key="dashboard-ssh-unavailable-{{ $server->getKey() }}"
            class="space-y-6"
        >
            <x-ssh.unavailable-alert
                :message="$sshErrorMessage"
                :retry-after="$sshRetryAfter"
                retry-action="retryConnection"
            />

            <x-card
                class="border border-base-300 bg-base-100/70
                       py-14 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center
                           rounded-2xl bg-base-200"
                >
                    <x-icon
                        name="o-server-stack"
                        class="size-8 text-base-content/30"
                    />
                </div>

                <h2 class="mt-5 text-lg font-semibold text-base-content">
                    اطلاعات سرور در دسترس نیست
                </h2>

                <p
                    class="mx-auto mt-2 max-w-lg text-sm leading-7
                           text-base-content/55"
                >
                    تا زمانی که ارتباط SSH دوباره برقرار نشود، دریافت اطلاعات
                    سیستم، وضعیت سرویس‌ها و مصرف منابع متوقف می‌ماند.
                </p>
            </x-card>
        </div>

    @elseif ($readinessIssue !== null)

        {{-- Persistent runtime readiness state --}}
        <div
            wire:key="dashboard-readiness-{{ $server->getKey() }}-{{ $readinessIssue }}"
            class="space-y-6"
        >
            <x-dashboard.readiness-alert
                :issue="$readinessIssue"
                :operating-system="$readinessOperatingSystem"
                retry-action="retryConnection"
                :edit-url="route('panel.servers.edit', $server)"
            />

            <x-card
                class="border border-base-300 bg-base-100/70
                       py-14 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center
                           rounded-2xl bg-base-200"
                >
                    <x-icon
                        name="o-shield-exclamation"
                        class="size-8 text-base-content/30"
                    />
                </div>

                <h2 class="mt-5 text-lg font-semibold text-base-content">
                    داشبورد موقتاً متوقف شده است
                </h2>

                <p
                    class="mx-auto mt-2 max-w-lg text-sm leading-7
                           text-base-content/55"
                >
                    xDeploy تا برطرف‌شدن مشکل آمادگی سرور، اطلاعات منابع،
                    سرویس‌ها و وضعیت سیستم را نمایش نمی‌دهد تا داده قدیمی یا
                    ناقص به‌عنوان وضعیت فعلی سرور ارائه نشود.
                </p>
            </x-card>
        </div>

    @elseif ($errorMessage !== null)

        {{-- Non-SSH dashboard error --}}
        <div
            wire:key="dashboard-error-{{ $server->getKey() }}"
        >
            <x-card
                class="border border-error/20 bg-error/5
                       py-14 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center
                           rounded-2xl bg-error/10"
                >
                    <x-icon
                        name="o-exclamation-triangle"
                        class="size-8 text-error"
                    />
                </div>

                <h2 class="mt-5 text-lg font-semibold text-base-content">
                    دریافت اطلاعات داشبورد ناموفق بود
                </h2>

                <p
                    class="mx-auto mt-2 max-w-lg text-sm leading-7
                           text-base-content/60"
                >
                    {{ $errorMessage }}
                </p>

                <x-button
                    label="تلاش مجدد"
                    icon="o-arrow-path"
                    wire:click="retryConnection"
                    wire:loading.attr="disabled"
                    wire:target="retryConnection"
                    spinner="retryConnection"
                    class="btn-error btn-outline mt-6"
                />
            </x-card>
        </div>

    @else

        {{-- Dashboard snapshot --}}
        <section
            wire:key="dashboard-snapshot-{{ $server->getKey() }}"
            class="grid grid-cols-1 gap-4
                   md:gap-5
                   xl:grid-cols-12 xl:gap-6"
        >
            {{-- Server overview --}}
            <div class="min-w-0 xl:col-span-8">
                <x-dashboard.server-overview
                    :overview="$overview"
                />
            </div>

            {{-- Service status --}}
            <div
                class="min-w-0 xl:col-span-4
                       [&>*]:h-full [&>*]:w-full"
            >
                <x-dashboard.services-card
                    :services="$overview['services'] ?? []"
                />
            </div>

            {{-- CPU information --}}
            <div class="min-w-0 xl:col-span-12">
                <x-dashboard.cpu-info
                    :cpu="$overview['cpu'] ?? []"
                />
            </div>

            {{-- Resource usage --}}
            <div class="min-w-0 xl:col-span-12">
                <x-dashboard.resource-usage
                    :memory="$overview['memory'] ?? []"
                    :disk="$overview['disk'] ?? []"
                    :load-average="$overview['loadAverage'] ?? []"
                />
            </div>
        </section>

    @endif

    {{-- Global loading indicator for heartbeat and retry --}}
    <div
        wire:loading.flex
        wire:target="checkConnection,retryConnection"
        class="fixed bottom-5 left-5 z-50 items-center gap-2
               rounded-2xl border border-base-300
               bg-base-100/90 px-4 py-3 text-xs
               text-base-content/60 shadow-lg backdrop-blur"
    >
        <span class="loading loading-spinner loading-xs"></span>

        <span wire:loading wire:target="checkConnection">
            در حال بررسی آمادگی سرور...
        </span>

        <span wire:loading wire:target="retryConnection">
            در حال بررسی مجدد سرور...
        </span>
    </div>
</div>
