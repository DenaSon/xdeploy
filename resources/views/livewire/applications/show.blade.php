@php
    $runtimeLoaded = $runtimeLoaded ?? true;

    $runtimePending = ! $runtimeLoaded
        && ! $operationActive;

    $operationStage = $operationStage ?? null;
    $operationStartedAt = $operationStartedAt ?? null;
    $operationStageUpdatedAt = $operationStageUpdatedAt ?? null;

    $state = $info['state']
        ?? 'unknown';


    /*
    |--------------------------------------------------------------------------
    | Operation presentation
    |--------------------------------------------------------------------------
    */

    $isInstallOperation = $operationType === 'install';

    $installStage = match ($operationStage) {
        'queued' => [
            'label' => 'در انتظار شروع',
            'message' => 'درخواست نصب ثبت شده و منتظر اجرای پردازش است.',
            'icon' => 'lucide.clock-3',
        ],

        'connecting' => [
            'label' => 'اتصال به سرور',
            'message' => 'ارتباط امن با سرور در حال برقرار شدن است.',
            'icon' => 'lucide.plug-zap',
        ],

        'checking_server' => [
            'label' => 'بررسی سرور',
            'message' => 'وضعیت سیستم و دسترسی‌های موردنیاز بررسی می‌شوند.',
            'icon' => 'lucide.server-cog',
        ],

        'preparing_server' => [
            'label' => 'آماده‌سازی سرور',
            'message' => 'تنظیمات پایه سرور برای ادامه نصب آماده می‌شوند.',
            'icon' => 'lucide.wrench',
        ],

        'installing_dependencies' => [
            'label' => 'آماده‌سازی پیش‌نیازها',
            'message' => 'بسته‌های موردنیاز برای اجرای برنامه در حال آماده‌سازی هستند.',
            'icon' => 'lucide.package-open',
        ],

        'preparing_platform' => [
            'label' => 'آماده‌سازی محیط اجرا',
            'message' => 'زیرساخت موردنیاز برنامه روی سرور آماده می‌شود.',
            'icon' => 'lucide.boxes',
        ],

        'installing_application' => [
            'label' => "نصب {$name}",
            'message' => 'فایل‌ها و تنظیمات برنامه در حال نصب و پیکربندی هستند.',
            'icon' => 'lucide.download',
        ],

        'starting_application' => [
            'label' => 'راه‌اندازی سرویس',
            'message' => "{$name} نصب شده و سرویس آن در حال راه‌اندازی است.",
            'icon' => 'lucide.circle-play',
        ],

        'completed' => [
            'label' => 'تکمیل نصب',
            'message' => 'نصب برنامه تکمیل شده و وضعیت نهایی در حال تأیید است.',
            'icon' => 'lucide.circle-check',
        ],

        default => [
            'label' => $operationStatus === 'pending'
                ? 'در انتظار شروع'
                : 'آماده‌سازی نصب',
            'message' => $operationStatus === 'pending'
                ? 'درخواست نصب ثبت شده و منتظر اجرای پردازش است.'
                : 'مراحل نصب روی سرور در حال انجام هستند.',
            'icon' => 'lucide.loader-circle',
        ],
    };

    $operationProgressLabel = match (true) {
        $isInstallOperation
            && $operationStatus === 'pending'
                => 'در انتظار نصب',

        $isInstallOperation
            => 'در حال نصب',

        $operationType === 'uninstall'
            && $operationStatus === 'pending'
                => 'در انتظار حذف',

        $operationType === 'uninstall'
            => 'در حال حذف',

        $operationType === 'start'
            && $operationStatus === 'pending'
                => 'در انتظار اجرا',

        $operationType === 'start'
            => 'در حال اجرا',

        $operationType === 'stop'
            && $operationStatus === 'pending'
                => 'در انتظار توقف',

        $operationType === 'stop'
            => 'در حال توقف',

        $operationType === 'restart'
            && $operationStatus === 'pending'
                => 'در انتظار راه‌اندازی مجدد',

        $operationType === 'restart'
            => 'در حال راه‌اندازی مجدد',

        default
            => 'در حال پردازش',
    };

    $operationProgressMessage = match (true) {
        $isInstallOperation
            => $installStage['message'],

        $operationType === 'uninstall'
            && $operationStatus === 'pending'
                => "درخواست حذف {$name} در صف اجرا قرار دارد.",

        $operationType === 'uninstall'
            => "حذف {$name} از سرور در حال انجام است.",

        $operationType === 'start'
            && $operationStatus === 'pending'
                => "درخواست اجرای {$name} در صف قرار دارد.",

        $operationType === 'start'
            => "اجرای {$name} روی سرور در حال انجام است.",

        $operationType === 'stop'
            && $operationStatus === 'pending'
                => "درخواست توقف {$name} در صف قرار دارد.",

        $operationType === 'stop'
            => "توقف {$name} روی سرور در حال انجام است.",

        $operationType === 'restart'
            && $operationStatus === 'pending'
                => "درخواست راه‌اندازی مجدد {$name} در صف قرار دارد.",

        $operationType === 'restart'
            => "راه‌اندازی مجدد {$name} روی سرور در حال انجام است.",

        default
            => 'عملیات برنامه در حال پردازش است.',
    };

    $operationStartedAgo = is_int($operationStartedAt)
        ? \Illuminate\Support\Carbon::createFromTimestamp($operationStartedAt)
            ->locale(app()->getLocale())
            ->diffForHumans()
        : null;

    $operationStageUpdatedAgo = is_int($operationStageUpdatedAt)
        ? \Illuminate\Support\Carbon::createFromTimestamp($operationStageUpdatedAt)
            ->locale(app()->getLocale())
            ->diffForHumans()
        : null;


    /*
    |--------------------------------------------------------------------------
    | Application status
    |--------------------------------------------------------------------------
    */

    $status = match (true) {
        $operationActive => [
            'label' => $operationProgressLabel,
            'icon' => 'lucide.loader-circle',
            'classes' => 'border-info/20 bg-info/10 text-info',
            'dot' => 'bg-info',
        ],

        $runtimePending => [
            'label' => 'در حال دریافت وضعیت',
            'icon' => 'lucide.loader-circle',
            'classes' => 'border-info/20 bg-info/10 text-info',
            'dot' => 'bg-info',
        ],

        default => match ($state) {
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
                'label' => 'نیازمند بررسی',
                'icon' => 'lucide.circle-help',
                'classes' => 'border-warning/20 bg-warning/10 text-warning',
                'dot' => 'bg-warning',
            ],
        },
    };


    /*
    |--------------------------------------------------------------------------
    | Application metadata
    |--------------------------------------------------------------------------
    */

    $version = $info['version']
        ?? null;

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
        wire:init="loadRuntime"
        aria-busy="{{ $runtimePending ? 'true' : 'false' }}"
        @if($operationActive)
            wire:poll.2s="pollOperation"
        @endif
        class="space-y-5"
    >
        {{-- Application overview --}}
        <section
            x-data="{ descriptionExpanded: false }"
            class="
                overflow-hidden
                rounded-2xl
                border border-base-300/80
                bg-base-100
                shadow-sm
                shadow-base-content/[0.015]
            "
        >
            {{-- Main application identity --}}
            <div
                class="
                    flex flex-col gap-4
                    px-4 py-4
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    sm:px-5 sm:py-5
                "
            >
                <div class="flex min-w-0 items-center gap-3">
                    {{-- Back --}}
                    <a
                        href="{{ route('panel.servers.applications.index', [
                            'server' => $serverId,
                        ]) }}"
                        wire:navigate
                        aria-label="بازگشت به برنامه‌ها"
                        class="
                            flex size-9 shrink-0
                            items-center justify-center
                            rounded-xl
                            text-base-content/40
                            transition-colors duration-150
                            hover:bg-base-200
                            hover:text-base-content
                        "
                    >
                        <x-icon
                            name="lucide.arrow-right"
                            class="!size-4 stroke-[1.7]"
                        />
                    </a>

                    {{-- Icon --}}
                    <div
                        class="
                            flex size-11 shrink-0
                            items-center justify-center
                            overflow-hidden rounded-xl
                            border border-primary/10
                            bg-primary/[0.055]
                            text-primary
                        "
                    >
                        @if($usesLucideIcon)
                            <x-icon
                                :name="$applicationIcon"
                                class="!size-5 stroke-[1.7]"
                            />
                        @elseif($applicationIcon !== null)
                            <img
                                src="{{ asset($applicationIcon) }}"
                                alt=""
                                class="size-7 object-contain"
                            />
                        @else
                            <x-icon
                                name="lucide.package"
                                class="!size-5 stroke-[1.7]"
                            />
                        @endif
                    </div>

                    {{-- Name --}}
                    <div class="min-w-0">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <h1
                                class="
                                    truncate
                                    text-lg font-semibold tracking-tight
                                    text-base-content
                                "
                            >
                                {{ $name }}
                            </h1>

                            {{-- Status --}}
                            <span
                                class="
                                    inline-flex shrink-0 items-center gap-1.5
                                    rounded-full border
                                    px-2.5 py-1
                                    text-[10px] font-medium
                                    {{ $status['classes'] }}
                                "
                            >
                                @if($runtimePending || $operationActive)
                                    <span class="loading loading-spinner loading-xs"></span>
                                @else
                                    <span
                                        class="size-1.5 rounded-full {{ $status['dot'] }}"
                                    ></span>
                                @endif

                                {{ $status['label'] }}
                            </span>
                        </div>

                        @if($shortDescription !== '')
                            <p
                                class="
                                    mt-1 max-w-2xl
                                    text-xs leading-6 text-base-content/45
                                    sm:text-sm
                                "
                            >
                                {{ $shortDescription }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Utilities --}}
                <div class="flex shrink-0 items-center gap-1">
                    @if($description !== null)
                        <div
                            class="
                                tooltip tooltip-bottom
                                before:z-50 before:whitespace-nowrap before:text-xs
                                after:z-50
                            "
                            data-tip="توضیحات برنامه"
                        >
                            <button
                                type="button"
                                @click="descriptionExpanded = ! descriptionExpanded"
                                :aria-expanded="descriptionExpanded.toString()"
                                :class="descriptionExpanded
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-base-content/40'"
                                aria-label="نمایش توضیحات {{ $name }}"
                                class="
                                    flex size-9 items-center justify-center
                                    rounded-xl
                                    transition-colors duration-150
                                    hover:bg-base-200 hover:text-primary
                                "
                            >
                                <x-icon
                                    name="lucide.info"
                                    class="!size-4 stroke-[1.7]"
                                />
                            </button>
                        </div>
                    @endif

                    @if(
                        $runtimeLoaded
                        && ! $sshUnavailable
                        && ! $operationActive
                    )
                        <div
                            class="
                                tooltip tooltip-bottom
                                before:z-50 before:whitespace-nowrap before:text-xs
                                after:z-50
                            "
                            data-tip="به‌روزرسانی وضعیت"
                        >
                            <button
                                type="button"
                                wire:click="refreshApplication"
                                wire:loading.attr="disabled"
                                wire:target="refreshApplication"
                                aria-label="به‌روزرسانی وضعیت برنامه"
                                class="
                                    flex size-9 items-center justify-center
                                    rounded-xl text-base-content/40
                                    transition-colors duration-150
                                    hover:bg-base-200 hover:text-primary
                                    disabled:pointer-events-none disabled:opacity-50
                                "
                            >
                                <span wire:loading.remove wire:target="refreshApplication">
                                    <x-icon
                                        name="lucide.refresh-cw"
                                        class="!size-4 stroke-[1.7]"
                                    />
                                </span>

                                <span
                                    wire:loading
                                    wire:target="refreshApplication"
                                    class="loading loading-spinner loading-xs"
                                ></span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Description --}}
            @if($description !== null)
                <div
                    x-cloak
                    x-show="descriptionExpanded"
                    x-collapse
                    class="border-t border-base-300/70"
                >
                    <div
                        class="
                            flex items-start gap-3
                            bg-base-200/25
                            px-4 py-4 sm:px-5
                        "
                    >
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-primary/[0.07] text-primary
                            "
                        >
                            <x-icon
                                name="lucide.info"
                                class="!size-4 stroke-[1.7]"
                            />
                        </span>

                        <div class="min-w-0">
                            <div class="text-[11px] font-medium text-base-content/40">
                                درباره برنامه
                            </div>

                            <p
                                class="
                                    mt-1 max-w-4xl
                                    text-sm leading-7 text-base-content/60
                                "
                            >
                                {{ $description }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Facts --}}
            @if($runtimePending)
                <div class="grid border-t border-base-300/70 sm:grid-cols-3">
                    @foreach([
                        ['lucide.activity', 'وضعیت'],
                        ['lucide.fingerprint', 'شناسه'],
                        ['lucide.tag', 'نسخه'],
                    ] as [$factIcon, $factLabel])
                        <div
                            class="
                                flex items-center gap-3
                                border-b border-base-300/70
                                px-4 py-3.5 last:border-b-0
                                sm:border-b-0 sm:border-l sm:last:border-l-0
                                sm:px-5
                            "
                        >
                            <span
                                class="
                                    flex size-8 shrink-0 items-center justify-center
                                    rounded-lg bg-base-200/60
                                    text-base-content/35
                                "
                            >
                                <x-icon
                                    :name="$factIcon"
                                    class="!size-4 stroke-[1.7]"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] text-base-content/35">
                                    {{ $factLabel }}
                                </div>

                                @if($factLabel === 'شناسه')
                                    <div
                                        dir="ltr"
                                        class="
                                            technical-value mt-0.5 truncate
                                            text-left text-xs font-medium
                                            text-base-content/60
                                        "
                                    >
                                        {{ $application }}
                                    </div>
                                @else
                                    <div class="skeleton mt-1.5 h-3 w-20"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif(! $sshUnavailable)
                <div class="grid border-t border-base-300/70 sm:grid-cols-3">
                    {{-- Status --}}
                    <div
                        class="
                            flex items-center gap-3
                            border-b border-base-300/70
                            px-4 py-3.5
                            sm:border-b-0 sm:border-l sm:px-5
                        "
                    >
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-base-200/60
                                text-base-content/45
                            "
                        >
                            <x-icon
                                :name="$status['icon']"
                                class="!size-4 stroke-[1.7]"
                            />
                        </span>

                        <div class="min-w-0">
                            <div class="text-[10px] text-base-content/35">وضعیت</div>
                            <div class="mt-0.5 truncate text-xs font-medium text-base-content">
                                {{ $status['label'] }}
                            </div>
                        </div>
                    </div>

                    {{-- Identifier --}}
                    <div
                        class="
                            flex items-center gap-3
                            border-b border-base-300/70
                            px-4 py-3.5
                            sm:border-b-0 sm:border-l sm:px-5
                        "
                    >
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-base-200/60
                                text-base-content/45
                            "
                        >
                            <x-icon
                                name="lucide.fingerprint"
                                class="!size-4 stroke-[1.7]"
                            />
                        </span>

                        <div class="min-w-0">
                            <div class="text-[10px] text-base-content/35">شناسه</div>
                            <div
                                dir="ltr"
                                class="
                                    technical-value mt-0.5 truncate
                                    text-left text-xs font-medium text-base-content
                                "
                            >
                                {{ $application }}
                            </div>
                        </div>
                    </div>

                    {{-- Version --}}
                    <div class="flex items-center gap-3 px-4 py-3.5 sm:px-5">
                        <span
                            class="
                                flex size-8 shrink-0 items-center justify-center
                                rounded-lg bg-base-200/60
                                text-base-content/45
                            "
                        >
                            <x-icon
                                name="lucide.tag"
                                class="!size-4 stroke-[1.7]"
                            />
                        </span>

                        <div class="min-w-0">
                            <div class="text-[10px] text-base-content/35">نسخه</div>
                            <div
                                dir="ltr"
                                class="
                                    technical-value mt-0.5 truncate
                                    text-left text-xs font-medium text-base-content
                                "
                            >
                                {{ $version ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- Result messages --}}
        @if(
            $successMessage !== null
            || $errorMessage !== null
        )
            <div class="space-y-3">
                @if($successMessage !== null)
                    <x-alert
                        icon="lucide.circle-check"
                        class="border border-success/20 bg-success/[0.07] text-success"
                    >
                        {{ $successMessage }}
                    </x-alert>
                @endif

                @if($errorMessage !== null)
                    <x-alert
                        icon="lucide.triangle-alert"
                        class="border border-error/20 bg-error/[0.07] text-error"
                    >
                        {{ $errorMessage }}
                    </x-alert>
                @endif
            </div>
        @endif

        {{-- Runtime loading --}}
        @if($runtimePending)
            <section
                role="status"
                aria-live="polite"
                class="
                    rounded-2xl border border-base-300/80
                    bg-base-100 p-5
                "
            >
                <div class="flex items-start gap-3">
                    <span
                        class="
                            flex size-10 shrink-0 items-center justify-center
                            rounded-xl bg-info/10 text-info
                        "
                    >
                        <span class="loading loading-spinner loading-sm"></span>
                    </span>

                    <div>
                        <h2 class="text-sm font-semibold text-base-content">
                            در حال بررسی وضعیت برنامه
                        </h2>

                        <p class="mt-1 text-sm leading-6 text-base-content/50">
                            وضعیت اجرا، نسخه و کنترل‌های قابل استفاده
                            از سرور دریافت می‌شوند.
                        </p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="skeleton h-3.5 w-2/3"></div>
                    <div class="skeleton h-3.5 w-1/2"></div>

                    <div class="flex gap-2 pt-1">
                        <div class="skeleton h-8 w-24 rounded-xl"></div>
                        <div class="skeleton h-8 w-20 rounded-xl"></div>
                    </div>
                </div>
            </section>

        {{-- SSH unavailable --}}
        @elseif($sshUnavailable)
            <x-ssh.unavailable-alert
                :message="$sshErrorMessage"
                :retry-after="$sshRetryAfter"
                retry-action="retryConnection"
            />

            <section
                class="
                    rounded-2xl border border-base-300/80 bg-base-100
                    px-6 py-10 text-center
                "
            >
                <span
                    class="
                        mx-auto flex size-11 items-center justify-center
                        rounded-xl bg-base-200/60 text-base-content/35
                    "
                >
                    <x-icon
                        name="lucide.unplug"
                        class="!size-5 stroke-[1.7]"
                    />
                </span>

                <h2 class="mt-3 text-base font-semibold text-base-content">
                    امکان دریافت وضعیت برنامه وجود ندارد
                </h2>

                <p
                    class="
                        mx-auto mt-1.5 max-w-lg
                        text-sm leading-7 text-base-content/50
                    "
                >
                    تا زمان بازیابی اتصال SSH، کنترل‌های برنامه
                    برای جلوگیری از اجرای عملیات در شرایط نامشخص
                    موقتاً غیرفعال هستند.
                </p>
            </section>

        {{-- Application controls --}}
        @else
            <section
                class="
                    overflow-hidden rounded-2xl
                    border border-base-300/80 bg-base-100
                    shadow-sm shadow-base-content/[0.015]
                "
            >
                {{-- Controls heading --}}
                <header
                    class="
                        flex flex-col gap-3
                        border-b border-base-300/70
                        px-4 py-4
                        sm:flex-row sm:items-center sm:justify-between sm:px-5
                    "
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="
                                flex size-9 shrink-0 items-center justify-center
                                rounded-xl bg-base-200/70 text-base-content/55
                            "
                        >
                            <x-icon
                                :name="$operationActive && $isInstallOperation
                                    ? 'lucide.package-open'
                                    : 'lucide.sliders-horizontal'"
                                class="!size-4 stroke-[1.7]"
                            />
                        </span>

                        <div>
                            <h2 class="text-sm font-semibold text-base-content sm:text-base">
                                {{ $operationActive && $isInstallOperation
                                    ? 'فرآیند نصب'
                                    : 'کنترل برنامه' }}
                            </h2>

                            <p class="mt-0.5 text-xs text-base-content/40">
                                @if($operationActive && $isInstallOperation)
                                    وضعیت مراحل نصب به‌صورت خودکار به‌روزرسانی می‌شود.
                                @else
                                    گزینه‌های قابل استفاده متناسب با وضعیت فعلی برنامه نمایش داده می‌شوند.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div
                        wire:loading
                        wire:target="install,uninstall,start,stop,restart"
                        class="items-center gap-2 text-xs text-base-content/40"
                    >
                        <span class="loading loading-spinner loading-xs"></span>
                        در حال پردازش
                    </div>
                </header>

                <div class="px-4 py-4 sm:px-5">
                    {{-- Active operation --}}
                    @if($operationActive)
                        @if($isInstallOperation)
                            <div
                                role="status"
                                aria-live="polite"
                                class="
                                    overflow-hidden rounded-2xl
                                    border border-info/15
                                    bg-info/[0.025]
                                "
                            >
                                <div
                                    class="
                                        flex flex-col gap-4
                                        px-4 py-4
                                        sm:px-5 sm:py-5
                                    "
                                >
                                    <div
                                        class="
                                            flex flex-col gap-3
                                            sm:flex-row sm:items-start sm:justify-between
                                        "
                                    >
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span
                                                class="
                                                    flex size-10 shrink-0 items-center justify-center
                                                    rounded-xl bg-info/10 text-info
                                                "
                                            >
                                                <span class="loading loading-spinner loading-sm"></span>
                                            </span>

                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-base-content sm:text-base">
                                                    نصب {{ $name }}
                                                </div>
                                                <p class="mt-0.5 text-xs text-base-content/45">
                                                    مراحل نصب روی سرور در حال اجرا هستند.
                                                </p>
                                            </div>
                                        </div>

                                        @if($operationStartedAgo !== null)
                                            <span
                                                class="
                                                    shrink-0 rounded-full
                                                    border border-base-300/70 bg-base-100/80
                                                    px-2.5 py-1
                                                    text-[10px] text-base-content/45
                                                "
                                            >
                                                شروع {{ $operationStartedAgo }}
                                            </span>
                                        @endif
                                    </div>

                                    <progress
                                        class="progress progress-primary h-1.5 w-full"
                                        aria-label="پیشرفت نصب {{ $name }}"
                                    ></progress>

                                    <div
                                        class="
                                            flex items-start gap-3
                                            rounded-xl border border-base-300/60
                                            bg-base-100/70
                                            px-3.5 py-3.5
                                            sm:px-4
                                        "
                                    >
                                        <span
                                            class="
                                                flex size-9 shrink-0 items-center justify-center
                                                rounded-xl bg-primary/[0.07] text-primary
                                            "
                                        >
                                            <x-icon
                                                :name="$installStage['icon']"
                                                class="!size-4 stroke-[1.7]"
                                            />
                                        </span>

                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-base-content">
                                                {{ $installStage['label'] }}
                                            </div>
                                            <p
                                                class="
                                                    mt-1 max-w-2xl
                                                    text-xs leading-6 text-base-content/50
                                                    sm:text-sm
                                                "
                                            >
                                                {{ $installStage['message'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="
                                        flex flex-wrap items-center gap-x-4 gap-y-1.5
                                        border-t border-base-300/60
                                        bg-base-200/20
                                        px-4 py-2.5
                                        text-[10px] text-base-content/35
                                        sm:px-5
                                    "
                                >
                                    @if($operationStatus === 'pending')
                                        <span>در انتظار اجرای پردازش</span>
                                    @elseif($operationStartedAgo !== null)
                                        <span>شروع نصب · {{ $operationStartedAgo }}</span>
                                    @endif

                                    @if($operationStageUpdatedAgo !== null)
                                        <span>
                                            آخرین تغییر مرحله · {{ $operationStageUpdatedAgo }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div
                                class="
                                    flex items-start gap-3 rounded-xl
                                    border border-info/15 bg-info/[0.04]
                                    px-4 py-3.5
                                "
                            >
                                <span
                                    class="
                                        loading loading-spinner loading-sm
                                        mt-0.5 shrink-0 text-info
                                    "
                                ></span>

                                <div>
                                    <div class="text-sm font-medium text-base-content">
                                        {{ $operationProgressLabel }}
                                    </div>

                                    <p
                                        class="
                                            mt-1 text-xs leading-6 text-base-content/50
                                            sm:text-sm
                                        "
                                    >
                                        {{ $operationProgressMessage }}
                                    </p>
                                </div>
                            </div>
                        @endif

                    {{-- Unknown --}}
                    @elseif($info['is_unknown'])
                        <div
                            class="
                                flex flex-col gap-4
                                sm:flex-row sm:items-center sm:justify-between
                            "
                        >
                            <div>
                                <h3 class="text-sm font-medium text-base-content">
                                    وضعیت فعلی برنامه مشخص نیست
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-base-content/50">
                                    برای دریافت آخرین وضعیت برنامه،
                                    اطلاعات آن را دوباره از سرور دریافت کنید.
                                </p>
                            </div>

                            <x-button
                                label="به‌روزرسانی وضعیت"
                                icon="lucide.refresh-cw"
                                wire:click="refreshApplication"
                                wire:loading.attr="disabled"
                                wire:target="refreshApplication"
                                spinner="refreshApplication"
                                class="btn-primary btn-sm shrink-0 rounded-xl"
                            />
                        </div>

                    {{-- Not installed --}}
                    @elseif($info['is_not_installed'])
                        <div
                            class="
                                flex flex-col gap-4
                                sm:flex-row sm:items-center sm:justify-between
                            "
                        >
                            <div>
                                <h3 class="text-sm font-medium text-base-content">
                                    {{ $name }} روی این سرور نصب نشده است
                                </h3>

                                <p
                                    class="
                                        mt-1 max-w-2xl
                                        text-sm leading-6 text-base-content/50
                                    "
                                >
                                    با شروع نصب، پیش‌نیازها و مراحل آماده‌سازی اولیه
                                    برنامه به‌صورت خودکار روی سرور انجام می‌شوند.
                                </p>
                            </div>

                            <x-button
                                label="نصب برنامه"
                                icon="lucide.download"
                                wire:click="install"
                                wire:loading.attr="disabled"
                                wire:target="install"
                                spinner="install"
                                class="btn-primary btn-sm shrink-0 rounded-xl"
                            />
                        </div>

                    {{-- Running --}}
                    @elseif($info['is_running'])
                        <div
                            class="
                                flex flex-col gap-4
                                sm:flex-row sm:items-center sm:justify-between
                            "
                        >
                            <div>
                                <div class="text-sm font-medium text-base-content">
                                    برنامه در حال اجرا است
                                </div>

                                <p class="mt-1 text-sm leading-6 text-base-content/50">
                                    در صورت نیاز می‌توانید اجرای برنامه را
                                    متوقف یا دوباره راه‌اندازی کنید.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-button
                                    label="راه‌اندازی مجدد"
                                    icon="lucide.rotate-cw"
                                    wire:click="restart"
                                    wire:loading.attr="disabled"
                                    wire:target="restart"
                                    spinner="restart"
                                    class="btn-primary btn-sm rounded-xl"
                                />

                                <x-button
                                    label="توقف"
                                    icon="lucide.square"
                                    wire:click="stop"
                                    wire:loading.attr="disabled"
                                    wire:target="stop"
                                    spinner="stop"
                                    class="btn-outline btn-sm rounded-xl"
                                />

                                <x-button
                                    label="حذف"
                                    icon="lucide.trash-2"
                                    wire:click="uninstall"
                                    wire:confirm="آیا از حذف کامل {{ $name }} مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                                    wire:loading.attr="disabled"
                                    wire:target="uninstall"
                                    spinner="uninstall"
                                    class="
                                        btn-ghost btn-sm rounded-xl
                                        text-error/70
                                        hover:bg-error/10 hover:text-error
                                    "
                                />
                            </div>
                        </div>

                    {{-- Installed but stopped --}}
                    @elseif($info['is_installed'])
                        <div
                            class="
                                flex flex-col gap-4
                                sm:flex-row sm:items-center sm:justify-between
                            "
                        >
                            <div>
                                <div class="text-sm font-medium text-base-content">
                                    برنامه نصب شده است
                                </div>

                                <p class="mt-1 text-sm leading-6 text-base-content/50">
                                    برنامه در حال حاضر اجرا نمی‌شود.
                                    برای استفاده از آن، اجرا را آغاز کنید.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-button
                                    label="اجرای برنامه"
                                    icon="lucide.play"
                                    wire:click="start"
                                    wire:loading.attr="disabled"
                                    wire:target="start"
                                    spinner="start"
                                    class="btn-primary btn-sm rounded-xl"
                                />

                                <x-button
                                    label="حذف"
                                    icon="lucide.trash-2"
                                    wire:click="uninstall"
                                    wire:confirm="آیا از حذف کامل {{ $name }} مطمئن هستید؟ این عملیات قابل بازگشت نیست."
                                    wire:loading.attr="disabled"
                                    wire:target="uninstall"
                                    spinner="uninstall"
                                    class="
                                        btn-ghost btn-sm rounded-xl
                                        text-error/70
                                        hover:bg-error/10 hover:text-error
                                    "
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Application-specific management --}}
            @if(
                $info['is_running']
                && ! $operationActive
            )
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
