@props([
    'server',
    'state' => 'unknown',
    'domain' => null,
])

@php
    $domain = is_string($domain) && trim($domain) !== ''
        ? trim($domain)
        : null;

    $presentation = match ($state) {
        'enabled' => [
            'label' => 'فعال',
            'icon' => 'lucide.globe-lock',
            'iconClasses' => 'bg-success/10 text-success',
            'badgeClasses' => 'border-success/20 bg-success/10 text-success',
            'description' => 'دامنه و HTTPS فعال است. مدیریت اتصال از بخش دامنه‌های سرور انجام می‌شود.',
        ],

        'disabled' => [
            'label' => 'تنظیم نشده',
            'icon' => 'lucide.globe-2',
            'iconClasses' => 'bg-base-200/70 text-base-content/45',
            'badgeClasses' => 'border-base-300 bg-base-200/70 text-base-content/55',
            'description' => 'هنوز دامنه‌ای برای Marzban متصل نشده است.',
        ],

        'managed_externally' => [
            'label' => 'مدیریت خارجی',
            'icon' => 'lucide.external-link',
            'iconClasses' => 'bg-info/10 text-info',
            'badgeClasses' => 'border-info/20 bg-info/10 text-info',
            'description' => 'HTTPS خارج از xDeploy مدیریت می‌شود. جزئیات اتصال را در بخش دامنه‌ها ببینید.',
        ],

        'misconfigured' => [
            'label' => 'نیازمند بررسی',
            'icon' => 'lucide.triangle-alert',
            'iconClasses' => 'bg-error/10 text-error',
            'badgeClasses' => 'border-error/20 bg-error/10 text-error',
            'description' => 'پیکربندی دامنه یا HTTPS نیاز به بررسی دارد.',
        ],

        default => [
            'label' => 'نامشخص',
            'icon' => 'lucide.circle-help',
            'iconClasses' => 'bg-warning/10 text-warning',
            'badgeClasses' => 'border-warning/20 bg-warning/10 text-warning',
            'description' => 'وضعیت دامنه در حال حاضر قابل تشخیص نیست.',
        ],
    };
@endphp

<section
    {{ $attributes->class([
        'overflow-hidden rounded-2xl',
        'border border-base-300',
        'bg-base-100',
    ]) }}
>
    <div
        class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
    >
        <div class="flex min-w-0 items-start gap-3.5">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $presentation['iconClasses'] }}"
            >
                <x-icon
                    :name="$presentation['icon']"
                    class="!size-4.5"
                />
            </div>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold text-base-content">
                        دامنه
                    </h3>

                    <span
                        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $presentation['badgeClasses'] }}"
                    >
                        {{ $presentation['label'] }}
                    </span>
                </div>

                @if ($domain !== null)
                    <p
                        dir="ltr"
                        class="technical-value mt-1.5 truncate text-left text-sm font-medium text-base-content/70"
                    >
                        {{ $domain }}
                    </p>
                @endif

                <p
                    class="mt-1 max-w-xl text-xs leading-6 text-base-content/45"
                >
                    {{ $presentation['description'] }}
                </p>
            </div>
        </div>

        <x-button
            label="مدیریت دامنه"
            icon="lucide.arrow-left"
            :link="route(
                'panel.servers.domains.index',
                ['server' => $server],
            )"
            wire:navigate
            class="btn-ghost btn-sm shrink-0 rounded-xl"
        />
    </div>
</section>
