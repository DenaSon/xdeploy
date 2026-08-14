@props([
    'issue',
    'operatingSystem' => null,
    'retryAction' => 'retryConnection',
    'editUrl' => null,
])

@php
    $config = match ($issue) {
        'password_change_required' => [
            'icon' => 'o-key',
            'title' => 'تغییر رمز عبور SSH الزامی است',
            'description' => 'اتصال SSH برقرار است، اما سیستم‌عامل تا زمان تغییر رمز عبور اجازه اجرای دستورات موردنیاز Coreflare را نمی‌دهد.',
            'hint' => 'یک‌بار مستقیماً از طریق SSH وارد سرور شوید و رمز را تغییر دهید. سپس رمز جدید را در بخش ویرایش سرور Coreflare ذخیره کنید و بررسی مجدد را بزنید.',
            'alertClass' => 'border border-warning/25 bg-warning/5',
            'titleClass' => 'text-warning',
            'buttonClass' => 'btn-warning btn-outline',
            'showEdit' => true,
        ],

        'unsupported_operating_system' => [
            'icon' => 'o-exclamation-triangle',
            'title' => 'سیستم‌عامل پشتیبانی نمی‌شود',
            'description' => filled($operatingSystem)
                ? "سیستم‌عامل شناسایی‌شده {$operatingSystem} است و در نسخه فعلی Coreflare پشتیبانی نمی‌شود."
                : 'سیستم‌عامل این سرور در نسخه فعلی Coreflare پشتیبانی نمی‌شود.',
            'hint' => 'در حال حاضر فقط Ubuntu و Debian برای داشبورد و عملیات مدیریتی Coreflare پشتیبانی می‌شوند.',
            'alertClass' => 'border border-error/20 bg-error/5',
            'titleClass' => 'text-error',
            'buttonClass' => 'btn-error btn-outline',
            'showEdit' => false,
        ],

        'command_unavailable' => [
            'icon' => 'o-command-line',
            'title' => 'امکان اجرای دستورات وجود ندارد',
            'description' => 'اتصال SSH برقرار است، اما Coreflare نمی‌تواند دستورات موردنیاز برای دریافت اطلاعات داشبورد را اجرا کند.',
            'hint' => 'وضعیت shell، محدودیت‌های حساب SSH و تنظیمات دسترسی کاربر روی سرور را بررسی کنید.',
            'alertClass' => 'border border-warning/25 bg-warning/5',
            'titleClass' => 'text-warning',
            'buttonClass' => 'btn-warning btn-outline',
            'showEdit' => false,
        ],

        'operating_system_inspection_failed' => [
            'icon' => 'o-exclamation-triangle',
            'title' => 'سیستم‌عامل قابل شناسایی نیست',
            'description' => 'اتصال SSH و اجرای دستور برقرار است، اما Coreflare نتوانست اطلاعات استاندارد سیستم‌عامل را از سرور دریافت کند.',
            'hint' => 'Coreflare برای ادامه باید بتواند اطلاعات /etc/os-release را بخواند. در حال حاضر فقط Ubuntu و Debian پشتیبانی می‌شوند.',
            'alertClass' => 'border border-warning/25 bg-warning/5',
            'titleClass' => 'text-warning',
            'buttonClass' => 'btn-warning btn-outline',
            'showEdit' => false,
        ],

        default => [
            'icon' => 'o-exclamation-triangle',
            'title' => 'سرور برای Coreflare آماده نیست',
            'description' => 'یکی از بررسی‌های آمادگی سرور ناموفق بود.',
            'hint' => 'وضعیت سرور را بررسی کرده و دوباره تلاش کنید.',
            'alertClass' => 'border border-warning/25 bg-warning/5',
            'titleClass' => 'text-warning',
            'buttonClass' => 'btn-warning btn-outline',
            'showEdit' => false,
        ],
    };
@endphp

<x-alert
    :icon="$config['icon']"
    class="{{ $config['alertClass'] }}"
>
    <div
        class="flex w-full flex-col gap-5
               lg:flex-row lg:items-center lg:justify-between"
    >
        <div class="min-w-0">
            <h3 class="font-semibold {{ $config['titleClass'] }}">
                {{ $config['title'] }}
            </h3>

            <p class="mt-1 text-sm leading-7 text-base-content/65">
                {{ $config['description'] }}
            </p>

            <p class="mt-2 text-xs leading-6 text-base-content/45">
                {{ $config['hint'] }}
            </p>
        </div>

        <div
            class="flex shrink-0 flex-col gap-2
                   sm:flex-row sm:items-center"
        >
            @if (
                $config['showEdit']
                && filled($editUrl)
            )
                <x-button
                    label="ویرایش اطلاعات اتصال"
                    icon="o-pencil-square"
                    :link="$editUrl"
                    class="btn-ghost"
                />
            @endif

            <x-button
                label="بررسی مجدد"
                icon="o-arrow-path"
                wire:click="{{ $retryAction }}"
                wire:target="{{ $retryAction }}"
                wire:loading.attr="disabled"
                spinner="{{ $retryAction }}"
                class="{{ $config['buttonClass'] }}"
            />
        </div>
    </div>
</x-alert>
