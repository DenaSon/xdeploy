<div>
    @php
        $state = $info['state'] ?? 'unknown';

        $statusLabel = match ($state) {
            'running' => 'در حال اجرا',
            'installed' => 'نصب‌شده',
            'not_installed' => 'نصب نشده',
            default => 'وضعیت نامشخص',
        };

        $statusClasses = match ($state) {
            'running' => 'border-success/20 bg-success/10 text-success',
            'installed' => 'border-info/20 bg-info/10 text-info',
            'not_installed' => 'border-base-300 bg-base-200 text-base-content/60',
            default => 'border-warning/20 bg-warning/10 text-warning',
        };

        $statusIcon = match ($state) {
            'running' => 'o-play',
            'installed' => 'o-check-circle',
            'not_installed' => 'o-arrow-down-tray',
            default => 'o-exclamation-triangle',
        };
    @endphp

    {{-- Header --}}
    <div
        class="flex flex-col gap-4
               sm:flex-row sm:items-center sm:justify-between"
    >

        <div class="flex items-center gap-3">

            <a
                href="{{ route('panel.applications.index') }}"
                wire:navigate
                class="flex size-9 items-center justify-center rounded-xl
                       border border-base-300 bg-base-100
                       text-base-content/60 transition
                       hover:border-primary/30 hover:text-primary"
            >
                <x-icon
                    name="o-arrow-right"
                    class="size-5"
                />
            </a>

            <div>
                <h1 class="text-2xl font-bold tracking-tight text-base-content">
                    {{ $name }}
                </h1>

                <p class="mt-1 text-sm text-base-content/60">
                    مدیریت نصب و وضعیت اجرای برنامه
                </p>
            </div>

        </div>

        @if (
            ! $serverMissing
            && ! $sshUnavailable
        )

            <x-button
                label="بروزرسانی وضعیت"
                icon="o-arrow-path"
                wire:click="refreshApplication"
                wire:loading.attr="disabled"
                wire:target="refreshApplication"
                spinner="refreshApplication"
                class="btn-ghost"
            />

        @endif

    </div>

    {{-- Persistent SSH state --}}
    @if ($sshUnavailable)

        <div class="mt-6">
            <x-ssh.unavailable-alert
                :message="$sshErrorMessage"
                :retry-after="$sshRetryAfter"
                retry-action="retryConnection"
            />
        </div>

    @endif

    {{-- Operation messages --}}
    @if (
        $successMessage !== null
        || $errorMessage !== null
    )

        <div class="mt-6 space-y-4">

            @if ($successMessage !== null)

                <x-alert
                    icon="o-check-circle"
                    class="border border-success/20
                           bg-success/10 text-success"
                >
                    {{ $successMessage }}
                </x-alert>

            @endif

            @if ($errorMessage !== null)

                <x-alert
                    icon="o-exclamation-triangle"
                    class="border border-error/20
                           bg-error/10 text-error"
                >
                    {{ $errorMessage }}
                </x-alert>

            @endif

        </div>

    @endif

    @if ($serverMissing)

        <x-card
            class="mt-6 border border-warning/20 bg-warning/5
                   py-12 text-center"
        >

            <div
                class="mx-auto flex size-14 items-center justify-center
                       rounded-2xl bg-warning/10"
            >
                <x-icon
                    name="o-server"
                    class="size-7 text-warning"
                />
            </div>

            <h2 class="mt-4 text-lg font-semibold text-base-content">
                سرور فعالی وجود ندارد
            </h2>

            <p
                class="mx-auto mt-2 max-w-md text-sm leading-7
                       text-base-content/60"
            >
                برای مدیریت برنامه‌ها، ابتدا باید یک سرور فعال در xDeploy
                تعریف کنید.
            </p>

            <a
                href="{{ route('panel.servers.index') }}"
                wire:navigate
                class="btn btn-primary mt-6"
            >
                مشاهده سرورها
            </a>

        </x-card>

    @elseif ($sshUnavailable)

        <x-card
            class="mt-6 border border-base-300 bg-base-100/70
                   py-12 text-center shadow-sm"
        >

            <div
                class="mx-auto flex size-14 items-center justify-center
                       rounded-2xl bg-base-200"
            >
                <x-icon
                    name="o-signal-slash"
                    class="size-7 text-base-content/30"
                />
            </div>

            <h2 class="mt-4 text-lg font-semibold text-base-content">
                وضعیت برنامه در دسترس نیست
            </h2>

            <p
                class="mx-auto mt-2 max-w-lg text-sm leading-7
                       text-base-content/60"
            >
                برای جلوگیری از اجرای عملیات با وضعیت نامشخص، کنترل‌های
                برنامه تا برقراری دوباره ارتباط SSH غیرفعال شده‌اند.
            </p>

        </x-card>

    @else

        {{-- Application summary --}}
        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <x-card
                class="border border-base-300 bg-base-100
                       shadow-sm xl:col-span-2"
            >

                <div
                    class="flex flex-col gap-6
                           sm:flex-row sm:items-start"
                >

                    <div
                        class="flex size-16 shrink-0 items-center
                               justify-center rounded-2xl bg-primary/10"
                    >
                        <x-icon
                            name="o-cube"
                            class="size-8 text-primary"
                        />
                    </div>

                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-3">

                            <h2 class="text-xl font-bold text-base-content">
                                {{ $name }}
                            </h2>

                            <span
                                class="inline-flex items-center gap-1.5
                                       rounded-full border px-3 py-1
                                       text-xs font-medium
                                       {{ $statusClasses }}"
                            >
                                <x-icon
                                    :name="$statusIcon"
                                    class="size-4"
                                />

                                {{ $statusLabel }}
                            </span>

                        </div>

                        <p
                            class="mt-2 text-sm leading-7
                                   text-base-content/60"
                        >
                            مدیریت چرخه عمر Marzban شامل نصب، اجرا، توقف،
                            راه‌اندازی مجدد و حذف برنامه.
                        </p>

                    </div>

                </div>

                <div
                    class="mt-8 grid grid-cols-1 gap-4
                           sm:grid-cols-2"
                >

                    <div
                        class="rounded-2xl border border-base-300
                               bg-base-200/30 p-4"
                    >

                        <p class="text-xs text-base-content/50">
                            شناسه برنامه
                        </p>

                        <p
                            class="mt-2 font-mono text-sm font-medium
                                   text-base-content"
                            dir="ltr"
                        >
                            {{ $application }}
                        </p>

                    </div>

                    <div
                        class="rounded-2xl border border-base-300
                               bg-base-200/30 p-4"
                    >

                        <p class="text-xs text-base-content/50">
                            نسخه
                        </p>

                        <p
                            class="mt-2 font-mono text-sm font-medium
                                   text-base-content"
                            dir="ltr"
                        >
                            {{ $info['version'] ?? 'نامشخص' }}
                        </p>

                    </div>

                </div>

            </x-card>

            {{-- Current status --}}
            <x-card
                class="border border-base-300 bg-base-100 shadow-sm"
            >

                <h3 class="font-semibold text-base-content">
                    وضعیت فعلی
                </h3>

                <div class="mt-5 space-y-4">

                    <div class="flex items-center justify-between gap-4">

                        <span class="text-sm text-base-content/60">
                            نصب
                        </span>

                        @if ($info['is_installed'])

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm font-medium text-success"
                            >
                                <x-icon
                                    name="o-check-circle"
                                    class="size-4"
                                />

                                نصب‌شده
                            </span>

                        @elseif ($info['is_unknown'])

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm font-medium text-warning"
                            >
                                <x-icon
                                    name="o-question-mark-circle"
                                    class="size-4"
                                />

                                نامشخص
                            </span>

                        @else

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm text-base-content/50"
                            >
                                <x-icon
                                    name="o-x-circle"
                                    class="size-4"
                                />

                                نصب نشده
                            </span>

                        @endif

                    </div>

                    <div class="h-px bg-base-300"></div>

                    <div class="flex items-center justify-between gap-4">

                        <span class="text-sm text-base-content/60">
                            اجرا
                        </span>

                        @if ($info['is_running'])

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm font-medium text-success"
                            >
                                <span
                                    class="size-2 rounded-full bg-success"
                                ></span>

                                فعال
                            </span>

                        @elseif ($info['is_installed'])

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm font-medium text-warning"
                            >
                                <span
                                    class="size-2 rounded-full bg-warning"
                                ></span>

                                متوقف
                            </span>

                        @else

                            <span
                                class="inline-flex items-center gap-1
                                       text-sm text-base-content/50"
                            >
                                <span
                                    class="size-2 rounded-full
                                           bg-base-content/30"
                                ></span>

                                غیرفعال
                            </span>

                        @endif

                    </div>

                </div>

            </x-card>

        </div>

        {{-- Application actions --}}
        <x-card
            class="mt-6 border border-base-300 bg-base-100 shadow-sm"
        >

            <div
                class="flex flex-col gap-2
                       sm:flex-row sm:items-center sm:justify-between"
            >

                <div>
                    <h3 class="font-semibold text-base-content">
                        عملیات برنامه
                    </h3>

                    <p class="mt-1 text-sm text-base-content/60">
                        عملیات قابل اجرا بر اساس وضعیت فعلی نمایش داده
                        می‌شوند.
                    </p>
                </div>

                <div
                    wire:loading
                    wire:target="install,uninstall,start,stop,restart"
                    class="text-sm text-base-content/60"
                >
                    <span
                        class="loading loading-spinner loading-sm"
                    ></span>

                    در حال انجام عملیات...
                </div>

            </div>

            <div class="mt-6 flex flex-wrap gap-3">

                @if ($info['is_unknown'])

                    <x-button
                        label="بروزرسانی وضعیت"
                        icon="o-arrow-path"
                        wire:click="refreshApplication"
                        wire:loading.attr="disabled"
                        wire:target="refreshApplication"
                        spinner="refreshApplication"
                        class="btn-primary"
                    />

                @elseif ($info['is_not_installed'])

                    <x-button
                        label="نصب و اجرا"
                        icon="o-arrow-down-tray"
                        wire:click="install"
                        wire:loading.attr="disabled"
                        wire:target="install"
                        spinner="install"
                        class="btn-primary"
                    />

                @elseif ($info['is_running'])

                    <x-button
                        label="راه‌اندازی مجدد"
                        icon="o-arrow-path"
                        wire:click="restart"
                        wire:loading.attr="disabled"
                        wire:target="restart"
                        spinner="restart"
                        class="btn-primary"
                    />

                    <x-button
                        label="توقف"
                        icon="o-stop"
                        wire:click="stop"
                        wire:loading.attr="disabled"
                        wire:target="stop"
                        spinner="stop"
                        class="btn-warning btn-outline"
                    />

                    <x-button
                        label="حذف"
                        icon="o-trash"
                        wire:click="uninstall"
                        wire:confirm="آیا از حذف کامل Marzban مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                        wire:loading.attr="disabled"
                        wire:target="uninstall"
                        spinner="uninstall"
                        class="btn-error btn-outline"
                    />

                @elseif ($info['is_installed'])

                    <x-button
                        label="اجرا"
                        icon="o-play"
                        wire:click="start"
                        wire:loading.attr="disabled"
                        wire:target="start"
                        spinner="start"
                        class="btn-primary"
                    />

                    <x-button
                        label="حذف"
                        icon="o-trash"
                        wire:click="uninstall"
                        wire:confirm="آیا از حذف کامل Marzban مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                        wire:loading.attr="disabled"
                        wire:target="uninstall"
                        spinner="uninstall"
                        class="btn-error btn-outline"
                    />

                @endif

            </div>

        </x-card>

        {{-- Future operations --}}
        @if ($info['is_running'])

            <x-card
                class="mt-6 border border-dashed border-base-300
                       bg-base-200/20"
            >

                <div class="flex items-start gap-4">

                    <div
                        class="flex size-11 shrink-0 items-center
                               justify-center rounded-2xl bg-base-200"
                    >
                        <x-icon
                            name="o-wrench-screwdriver"
                            class="size-5 text-base-content/50"
                        />
                    </div>

                    <div>
                        <h3 class="font-semibold text-base-content">
                            تنظیمات و ابزارهای Marzban
                        </h3>

                        <p
                            class="mt-1 text-sm leading-7
                                   text-base-content/60"
                        >
                            ساخت مدیر، پشتیبان‌گیری، SSL، تنظیمات Xray و سایر
                            ابزارهای مدیریتی در نسخه‌های بعدی به این بخش
                            اضافه خواهند شد.
                        </p>
                    </div>

                </div>

            </x-card>

        @endif

    @endif
</div>
