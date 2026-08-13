@php
    $state = $info['state'] ?? 'unknown';

    $operationProgressLabel = match (true) {
        $operationType === 'install' && $operationStatus === 'pending'
            => 'در صف نصب',
        $operationType === 'install'
            => 'در حال نصب',
        $operationType === 'uninstall' && $operationStatus === 'pending'
            => 'در صف حذف',
        $operationType === 'uninstall'
            => 'در حال حذف',
        default => 'در حال انجام عملیات',
    };

    $operationProgressMessage = match (true) {
        $operationType === 'install' && $operationStatus === 'pending'
            => "نصب {$name} در صف اجرا قرار دارد.",
        $operationType === 'install'
            => "xDeploy در حال نصب و راه‌اندازی {$name} روی سرور است.",
        $operationType === 'uninstall' && $operationStatus === 'pending'
            => "حذف {$name} در صف اجرا قرار دارد.",
        $operationType === 'uninstall'
            => "xDeploy در حال حذف {$name} از سرور است.",
        default => 'عملیات برنامه در پس‌زمینه در حال انجام است.',
    };

    $status = $operationActive
        ? [
            'label' => $operationProgressLabel,
            'icon' => 'lucide.loader-circle',
            'classes' => 'border-info/20 bg-info/10 text-info',
            'dot' => 'bg-info',
        ]
        : match ($state) {
            'running' => [
                'label' => 'در حال اجرا',
                'icon' => 'lucide.circle-play',
                'classes' => 'border-success/20 bg-success/10 text-success',
                'dot' => 'bg-success',
            ],

            'installed' => [
                'label' => 'نصب‌شده',
                'icon' => 'lucide.circle-check',
                'classes' => 'border-info/20 bg-info/10 text-info',
                'dot' => 'bg-info',
            ],

            'not_installed' => [
                'label' => 'نصب نشده',
                'icon' => 'lucide.package-plus',
                'classes' => 'border-base-300 bg-base-200/70 text-base-content/55',
                'dot' => 'bg-base-content/30',
            ],

            default => [
                'label' => 'وضعیت نامشخص',
                'icon' => 'lucide.circle-help',
                'classes' => 'border-warning/20 bg-warning/10 text-warning',
                'dot' => 'bg-warning',
            ],
        };

    $version = $info['version'] ?? null;

    $version = is_string($version)
        && trim($version) !== ''
            ? trim($version)
            : null;

    $applicationIcon = is_string($icon)
        && trim($icon) !== ''
            ? trim($icon)
            : null;

    $usesLucideIcon = $applicationIcon !== null
        && str_starts_with(
            $applicationIcon,
            'lucide.',
        );
@endphp

<x-servers.workspace :server="$server">

    <div
        class="space-y-5"
        @if ($operationActive)
            wire:poll.2s="pollOperation"
        @endif
    >

        {{-- Application context --}}
        <section
            x-data="{
                descriptionExpanded: false,
            }"
            class="overflow-hidden rounded-2xl
                   border border-base-300
                   bg-base-100"
        >
            {{-- Main application header --}}
            <div
                class="flex flex-col gap-4
                       px-5 py-5
                       sm:flex-row
                       sm:items-center
                       sm:justify-between
                       sm:px-6"
            >
                {{-- Identity --}}
                <div
                    class="flex min-w-0
                           items-center gap-3.5"
                >
                    {{-- Back --}}
                    <a
                        href="{{ route('panel.servers.applications.index', [
                            'server' => $serverId,
                        ]) }}"
                        wire:navigate
                        aria-label="بازگشت به برنامه‌ها"
                        class="flex size-9 shrink-0
                               items-center justify-center
                               rounded-xl
                               border border-base-300
                               bg-base-100
                               text-base-content/45
                               transition-colors duration-150
                               hover:border-primary/20
                               hover:bg-primary/5
                               hover:text-primary"
                    >
                        <x-icon
                            name="lucide.arrow-right"
                            class="size-4"
                        />
                    </a>

                    {{-- Application icon --}}
                    <div
                        class="flex size-12 shrink-0
                               items-center justify-center
                               overflow-hidden rounded-xl
                               border border-primary/15
                               bg-primary/[0.06]"
                    >
                        @if ($usesLucideIcon)

                            <x-icon
                                :name="$applicationIcon"
                                class="size-5.5 text-primary"
                            />

                        @elseif ($applicationIcon !== null)

                            <img
                                src="{{ asset($applicationIcon) }}"
                                alt="{{ $name }}"
                                class="size-8 object-contain"
                            />

                        @else

                            <x-icon
                                name="lucide.package"
                                class="size-5.5 text-primary"
                            />

                        @endif
                    </div>

                    {{-- Application title --}}
                    <div class="min-w-0">

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >
                            <h1
                                class="truncate text-lg
                                       font-semibold
                                       text-base-content"
                            >
                                {{ $name }}
                            </h1>

                            <span
                                class="inline-flex items-center
                                       gap-1.5 rounded-full
                                       border px-2.5 py-1
                                       text-xs font-medium
                                       {{ $status['classes'] }}"
                            >
                                <span
                                    class="size-1.5 rounded-full
                                           {{ $status['dot'] }}"
                                ></span>

                                {{ $status['label'] }}
                            </span>
                        </div>

                        @if ($shortDescription !== '')

                            <p
                                class="mt-1 max-w-2xl
                                       text-sm leading-6
                                       text-base-content/50"
                            >
                                {{ $shortDescription }}
                            </p>

                        @endif
                    </div>
                </div>

                {{-- Header actions --}}
                <div
                    class="flex shrink-0
                           items-center gap-1"
                >
                    {{-- Description --}}
                    @if ($description !== null)

                        <div
                            class="tooltip tooltip-bottom
                                   before:z-50
                                   before:whitespace-nowrap
                                   before:text-xs
                                   after:z-50"
                            data-tip="درباره برنامه"
                        >
                            <button
                                type="button"
                                @click="
                                    descriptionExpanded =
                                        ! descriptionExpanded
                                "
                                x-bind:aria-expanded="
                                    descriptionExpanded.toString()
                                "
                                x-bind:class="{
                                    'border-primary/20 bg-primary/10 text-primary':
                                        descriptionExpanded,
                                }"
                                aria-label="اطلاعات بیشتر درباره {{ $name }}"
                                class="flex size-9
                                       items-center justify-center
                                       rounded-xl
                                       border border-transparent
                                       text-base-content/45
                                       transition-colors duration-150
                                       hover:border-base-300
                                       hover:bg-base-200/60
                                       hover:text-primary"
                            >
                                <x-icon
                                    name="lucide.info"
                                    class="size-4"
                                />
                            </button>
                        </div>

                    @endif

                    {{-- Refresh runtime --}}
                    @if (! $sshUnavailable && ! $operationActive)

                        <div
                            class="tooltip tooltip-bottom
                                   before:z-50
                                   before:whitespace-nowrap
                                   before:text-xs
                                   after:z-50"
                            data-tip="بروزرسانی وضعیت"
                        >
                            <button
                                type="button"
                                wire:click="refreshApplication"
                                wire:loading.attr="disabled"
                                wire:target="refreshApplication"
                                aria-label="بروزرسانی وضعیت برنامه"
                                class="flex size-9
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
                                    wire:target="refreshApplication"
                                >
                                    <x-icon
                                        name="lucide.refresh-cw"
                                        class="size-4"
                                    />
                                </span>

                                <span
                                    wire:loading
                                    wire:target="refreshApplication"
                                    class="loading loading-spinner
                                           loading-xs"
                                ></span>
                            </button>
                        </div>

                    @endif
                </div>
            </div>

            {{-- Full description --}}
            @if ($description !== null)

                <div
                    x-cloak
                    x-show="descriptionExpanded"
                    x-collapse
                    class="border-t border-base-300"
                >
                    <div
                        class="flex items-start gap-3
                               bg-base-200/25
                               px-5 py-4
                               sm:px-6"
                    >
                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg
                                   bg-primary/8
                                   text-primary"
                        >
                            <x-icon
                                name="lucide.info"
                                class="size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium
                                       text-base-content/55"
                            >
                                درباره {{ $name }}
                            </p>

                            <p
                                class="mt-1.5 max-w-4xl
                                       text-sm leading-7
                                       text-base-content/60"
                            >
                                {{ $description }}
                            </p>
                        </div>
                    </div>
                </div>

            @endif

            {{-- Runtime facts --}}
            @if (! $sshUnavailable && ! $operationActive)

                <div
                    class="grid grid-cols-1
                           border-t border-base-300
                           sm:grid-cols-3"
                >
                    {{-- Runtime status --}}
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
                                   bg-base-200/60"
                        >
                            <x-icon
                                :name="$status['icon']"
                                class="size-4
                                       text-base-content/45"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                وضعیت
                            </p>

                            <p
                                class="mt-0.5 truncate
                                       text-sm font-medium
                                       text-base-content"
                            >
                                {{ $status['label'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Application identifier --}}
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
                                   bg-base-200/60"
                        >
                            <x-icon
                                name="lucide.fingerprint"
                                class="size-4
                                       text-base-content/45"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                شناسه برنامه
                            </p>

                            <p
                                dir="ltr"
                                class="technical-value
                                       mt-0.5 truncate text-left
                                       text-sm font-medium
                                       text-base-content"
                            >
                                {{ $application }}
                            </p>
                        </div>
                    </div>

                    {{-- Version --}}
                    <div
                        class="flex items-center gap-3
                               px-5 py-3.5
                               sm:px-6"
                    >
                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg
                                   bg-base-200/60"
                        >
                            <x-icon
                                name="lucide.tag"
                                class="size-4
                                       text-base-content/45"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                نسخه
                            </p>

                            <p
                                dir="ltr"
                                class="technical-value
                                       mt-0.5 truncate text-left
                                       text-sm font-medium
                                       text-base-content"
                            >
                                {{ $version ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>

            @endif
        </section>

        {{-- Persistent SSH state --}}
        @if ($sshUnavailable)

            <x-ssh.unavailable-alert
                :message="$sshErrorMessage"
                :retry-after="$sshRetryAfter"
                retry-action="retryConnection"
            />

        @endif

        {{-- Background operation state --}}
        @if ($operationActive)

            <x-alert
                icon="lucide.clock-3"
                class="border border-info/20
                       bg-info/[0.08]
                       text-info"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="loading loading-spinner
                               loading-sm shrink-0"
                    ></span>

                    <div>
                        <p class="font-medium">
                            {{ $operationProgressLabel }}
                        </p>

                        <p class="mt-0.5 text-sm opacity-80">
                            {{ $operationProgressMessage }}
                            می‌توانید این صفحه را ترک کنید؛ عملیات در پس‌زمینه ادامه پیدا می‌کند.
                        </p>
                    </div>
                </div>
            </x-alert>

        @endif

        {{-- Operation feedback --}}
        @if (
            $successMessage !== null
            || $errorMessage !== null
        )

            <div class="space-y-3">

                @if ($successMessage !== null)

                    <x-alert
                        icon="lucide.circle-check"
                        class="border border-success/20
                               bg-success/10
                               text-success"
                    >
                        {{ $successMessage }}
                    </x-alert>

                @endif

                @if ($errorMessage !== null)

                    <x-alert
                        icon="lucide.triangle-alert"
                        class="border border-error/20
                               bg-error/10
                               text-error"
                    >
                        {{ $errorMessage }}
                    </x-alert>

                @endif

            </div>

        @endif

        @if ($sshUnavailable)

            {{-- Runtime unavailable --}}
            <section
                class="rounded-2xl
                       border border-base-300
                       bg-base-100
                       px-6 py-10
                       text-center"
            >
                <div
                    class="mx-auto flex size-11
                           items-center justify-center
                           rounded-xl
                           bg-base-200/60"
                >
                    <x-icon
                        name="lucide.unplug"
                        class="size-5
                               text-base-content/35"
                    />
                </div>

                <h2
                    class="mt-3 text-base
                           font-semibold
                           text-base-content"
                >
                    وضعیت برنامه در دسترس نیست
                </h2>

                <p
                    class="mx-auto mt-1.5
                           max-w-lg
                           text-sm leading-7
                           text-base-content/50"
                >
                    تا برقراری دوباره ارتباط SSH،
                    عملیات برنامه برای جلوگیری از اجرای
                    دستور با وضعیت نامشخص غیرفعال است.
                </p>
            </section>

        @else

            {{-- Lifecycle operations --}}
            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-base-300
                       bg-base-100"
            >
                <header
                    class="flex flex-col gap-3
                           border-b border-base-300
                           px-5 py-4
                           sm:flex-row
                           sm:items-center
                           sm:justify-between
                           sm:px-6"
                >
                    <div
                        class="flex items-start gap-3"
                    >
                        <div
                            class="flex size-9 shrink-0
                                   items-center justify-center
                                   rounded-xl
                                   bg-base-200/70"
                        >
                            <x-icon
                                name="lucide.power"
                                class="size-4
                                       text-base-content/60"
                            />
                        </div>

                        <div>
                            <h2
                                class="text-base font-semibold
                                       text-base-content"
                            >
                                عملیات برنامه
                            </h2>

                            <p
                                class="mt-0.5 text-sm
                                       text-base-content/50"
                            >
                                عملیات در دسترس بر اساس وضعیت فعلی برنامه
                            </p>
                        </div>
                    </div>

                    <div
                        wire:loading
                        wire:target="install,uninstall,start,stop,restart"
                        class="flex items-center gap-2
                               text-xs
                               text-base-content/45"
                    >
                        <span
                            class="loading loading-spinner
                                   loading-xs"
                        ></span>

                        <span>
                            در حال انجام عملیات
                        </span>
                    </div>
                </header>

                <div
                    class="px-5 py-4
                           sm:px-6"
                >
                    {{-- Background mutation --}}
                    @if ($operationActive)

                        <div
                            class="flex items-center gap-3
                                   rounded-xl
                                   bg-base-200/40
                                   px-4 py-3"
                        >
                            <span
                                class="loading loading-spinner
                                       loading-sm shrink-0
                                       text-info"
                            ></span>

                            <div>
                                <p
                                    class="text-sm font-medium
                                           text-base-content"
                                >
                                    {{ $operationProgressLabel }}
                                </p>

                                <p
                                    class="mt-0.5 text-sm
                                           text-base-content/50"
                                >
                                    تا پایان عملیات، سایر تغییرات این برنامه موقتاً غیرفعال هستند.
                                </p>
                            </div>
                        </div>

                    {{-- Unknown --}}
                    @elseif ($info['is_unknown'])

                        <div
                            class="flex flex-col gap-3
                                   sm:flex-row
                                   sm:items-center
                                   sm:justify-between"
                        >
                            <p
                                class="text-sm
                                       text-base-content/55"
                            >
                                وضعیت برنامه مشخص نیست.
                                ابتدا اطلاعات را دوباره دریافت کنید.
                            </p>

                            <x-button
                                label="بروزرسانی وضعیت"
                                icon="lucide.refresh-cw"
                                wire:click="refreshApplication"
                                wire:loading.attr="disabled"
                                wire:target="refreshApplication"
                                spinner="refreshApplication"
                                class="btn-primary btn-sm
                                       rounded-xl"
                            />
                        </div>

                        {{-- Not installed --}}
                    @elseif ($info['is_not_installed'])

                        <div
                            class="flex flex-col gap-4
                                   sm:flex-row
                                   sm:items-center
                                   sm:justify-between"
                        >
                            <div>
                                <h3
                                    class="text-sm font-medium
                                           text-base-content"
                                >
                                    {{ $name }} هنوز روی این سرور نصب نشده است
                                </h3>

                                <p
                                    class="mt-1 text-sm
                                           leading-6
                                           text-base-content/50"
                                >
                                    xDeploy مراحل نصب و راه‌اندازی اولیه
                                    برنامه را روی سرور انجام می‌دهد.
                                </p>
                            </div>

                            <x-button
                                label="نصب برنامه"
                                icon="lucide.download"
                                wire:click="install"
                                wire:loading.attr="disabled"
                                wire:target="install"
                                spinner="install"
                                class="btn-primary btn-sm
                                       shrink-0 rounded-xl"
                            />
                        </div>

                        {{-- Running --}}
                    @elseif ($info['is_running'])

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >
                            <x-button
                                label="راه‌اندازی مجدد"
                                icon="lucide.rotate-cw"
                                wire:click="restart"
                                wire:loading.attr="disabled"
                                wire:target="restart"
                                spinner="restart"
                                class="btn-primary btn-sm
                                       rounded-xl"
                            />

                            <x-button
                                label="توقف"
                                icon="lucide.square"
                                wire:click="stop"
                                wire:loading.attr="disabled"
                                wire:target="stop"
                                spinner="stop"
                                class="btn-outline btn-sm
                                       rounded-xl"
                            />

                            <x-button
                                label="حذف برنامه"
                                icon="lucide.trash-2"
                                wire:click="uninstall"
                                wire:confirm="آیا از حذف کامل {{ $name }} مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                                wire:loading.attr="disabled"
                                wire:target="uninstall"
                                spinner="uninstall"
                                class="btn-error btn-outline
                                       btn-sm rounded-xl"
                            />
                        </div>

                        {{-- Installed / stopped --}}
                    @elseif ($info['is_installed'])

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >
                            <x-button
                                label="اجرای برنامه"
                                icon="lucide.play"
                                wire:click="start"
                                wire:loading.attr="disabled"
                                wire:target="start"
                                spinner="start"
                                class="btn-primary btn-sm
                                       rounded-xl"
                            />

                            <x-button
                                label="حذف برنامه"
                                icon="lucide.trash-2"
                                wire:click="uninstall"
                                wire:confirm="آیا از حذف کامل {{ $name }} مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                                wire:loading.attr="disabled"
                                wire:target="uninstall"
                                spinner="uninstall"
                                class="btn-error btn-outline
                                       btn-sm rounded-xl"
                            />
                        </div>

                    @endif
                </div>
            </section>

            {{-- Application-specific management --}}
            @if ($info['is_running'] && ! $operationActive)

                <livewire:is
                    :component="$managementPanel"
                    :server-id="$serverId"
                    :key="'application-management-panel-'
                        .$application.'-'
                        .$serverId.'-'
                        .$managementPanelRevision"
                />

            @endif

        @endif

    </div>

</x-servers.workspace>
