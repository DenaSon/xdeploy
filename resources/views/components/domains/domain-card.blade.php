@props([
    'domain' => null,
    'application' => 'Marzban',
    'state' => 'unknown',
    'openUrl' => null,
    'applicationUrl' => null,
    'manageEndpointId' => null,
    'removing' => false,
])

@php
    $domain = is_string($domain) && trim($domain) !== ''
        ? trim($domain)
        : null;

    $openUrl = is_string($openUrl) && trim($openUrl) !== ''
        ? trim($openUrl)
        : null;

    $presentation = match ($state) {
        'enabled' => [
            'label' => 'فعال',
            'description' => 'دامنه متصل است و HTTPS با موفقیت در دسترس قرار دارد.',
            'icon' => 'lucide.globe-lock',
            'iconClasses' => 'bg-success/10 text-success',
            'badgeClasses' => 'border-success/20 bg-success/10 text-success',
            'dotClasses' => 'bg-success',
        ],

        'pending' => [
            'label' => 'در انتظار راه‌اندازی',
            'description' => 'دامنه به برنامه تخصیص داده شده است؛ بررسی DNS و فعال‌سازی HTTPS را تکمیل کنید.',
            'icon' => 'lucide.globe-2',
            'iconClasses' => 'bg-warning/10 text-warning',
            'badgeClasses' => 'border-warning/20 bg-warning/10 text-warning',
            'dotClasses' => 'bg-warning',
        ],

        'checking' => [
            'label' => 'در حال بررسی',
            'description' => 'اتصال دامنه ثبت شده و وضعیت واقعی HTTPS از سرور در حال دریافت است.',
            'icon' => 'lucide.loader-circle',
            'iconClasses' => 'bg-info/10 text-info',
            'badgeClasses' => 'border-info/20 bg-info/10 text-info',
            'dotClasses' => 'bg-info',
        ],

        'managed_externally' => [
            'label' => 'مدیریت خارجی',
            'description' => 'HTTPS شناسایی شده اما خارج از xDeploy مدیریت می‌شود.',
            'icon' => 'lucide.external-link',
            'iconClasses' => 'bg-info/10 text-info',
            'badgeClasses' => 'border-info/20 bg-info/10 text-info',
            'dotClasses' => 'bg-info',
        ],

        'misconfigured' => [
            'label' => 'نیازمند بررسی',
            'description' => 'وضعیت ثبت‌شده دامنه با پیکربندی فعلی سرور هم‌خوان نیست.',
            'icon' => 'lucide.triangle-alert',
            'iconClasses' => 'bg-error/10 text-error',
            'badgeClasses' => 'border-error/20 bg-error/10 text-error',
            'dotClasses' => 'bg-error',
        ],

        default => [
            'label' => 'نامشخص',
            'description' => 'وضعیت دامنه در حال حاضر قابل تشخیص نیست.',
            'icon' => 'lucide.circle-help',
            'iconClasses' => 'bg-warning/10 text-warning',
            'badgeClasses' => 'border-warning/20 bg-warning/10 text-warning',
            'dotClasses' => 'bg-warning',
        ],
    };
@endphp

<article
    {{ $attributes->class([
        'overflow-hidden rounded-2xl',
        'border border-base-300',
        'bg-base-100',
    ]) }}
>
    <div class="p-5 sm:p-6">
        <div
            class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex min-w-0 items-start gap-3.5">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $presentation['iconClasses'] }}"
                >
                    <x-icon
                        :name="$presentation['icon']"
                        class="!size-4.5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            dir="ltr"
                            class="technical-value truncate text-base font-semibold text-base-content"
                        >
                            {{ $domain ?? 'دامنه شناسایی نشد' }}
                        </h2>

                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $presentation['badgeClasses'] }}"
                        >
                            <span
                                class="size-1.5 rounded-full {{ $presentation['dotClasses'] }}"
                            ></span>

                            {{ $presentation['label'] }}
                        </span>
                    </div>

                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-base-content/45"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon
                                name="lucide.package"
                                class="!size-3.5"
                            />

                            {{ $application }}
                        </span>

                        @if ($state === 'enabled')
                            <span class="inline-flex items-center gap-1.5 text-success">
                                <x-icon
                                    name="lucide.shield-check"
                                    class="!size-3.5"
                                />

                                HTTPS فعال
                            </span>
                        @elseif ($state === 'pending')
                            <span class="inline-flex items-center gap-1.5 text-warning">
                                <x-icon
                                    name="lucide.clock-3"
                                    class="!size-3.5"
                                />

                                HTTPS هنوز فعال نشده
                            </span>
                        @endif
                    </div>

                    <p
                        class="mt-3 max-w-2xl text-sm leading-7 text-base-content/50"
                    >
                        {{ $presentation['description'] }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($applicationUrl !== null)
                    <x-button
                        label="مشاهده برنامه"
                        icon="lucide.package-open"
                        :link="$applicationUrl"
                        wire:navigate
                        class="btn-ghost btn-sm rounded-xl"
                    />
                @endif

                @if ($manageEndpointId !== null && $state === 'pending')
                    <x-button
                        label="مدیریت"
                        icon="lucide.settings-2"
                        wire:click="manageEndpoint({{ (int) $manageEndpointId }})"
                        class="btn-ghost btn-sm rounded-xl"
                    />
                @endif

                @if ($manageEndpointId !== null && $state !== 'pending')
                    <x-button
                        :label="$removing ? 'در حال حذف' : 'حذف دامنه'"
                        icon="lucide.unlink"
                        wire:click="removeEndpoint({{ (int) $manageEndpointId }})"
                        spinner="removeEndpoint"
                        :disabled="$removing"
                        wire:confirm="دامنه {{ $domain }} از {{ $application }} حذف شود؟ برنامه روی سرور باقی می‌ماند اما دسترسی عمومی این دامنه و HTTPS آن غیرفعال می‌شود."
                        class="btn-error btn-outline btn-sm rounded-xl"
                    />
                @endif

                @if ($openUrl !== null)
                    <x-button
                        label="باز کردن"
                        icon="lucide.external-link"
                        :link="$openUrl"
                        external
                        class="btn-primary btn-sm rounded-xl"
                    />
                @endif
            </div>
        </div>
    </div>

    @if ($state === 'enabled')
        <div
            class="grid grid-cols-2 border-t border-base-300 bg-base-200/20"
        >
            <div class="flex items-center gap-2.5 border-l border-base-300 px-5 py-3.5 sm:px-6">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success"
                >
                    <x-icon
                        name="lucide.network"
                        class="!size-3.5"
                    />
                </span>

                <div>
                    <div class="text-[10px] text-base-content/35">
                        DNS
                    </div>

                    <div class="mt-0.5 text-xs font-medium text-success">
                        متصل
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 px-5 py-3.5 sm:px-6">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success"
                >
                    <x-icon
                        name="lucide.lock-keyhole"
                        class="!size-3.5"
                    />
                </span>

                <div>
                    <div class="text-[10px] text-base-content/35">
                        HTTPS
                    </div>

                    <div class="mt-0.5 text-xs font-medium text-success">
                        فعال
                    </div>
                </div>
            </div>
        </div>
    @elseif ($state === 'pending')
        <div
            class="flex items-center gap-2.5 border-t border-warning/15 bg-warning/[0.035] px-5 py-3.5 sm:px-6"
        >
            <x-icon
                name="lucide.info"
                class="!size-3.5 shrink-0 text-warning"
            />

            <p class="text-xs leading-6 text-base-content/55">
                پس از تنظیم رکورد A، از «مدیریت» بررسی DNS را اجرا و HTTPS را فعال کنید.
            </p>
        </div>
    @endif
</article>
