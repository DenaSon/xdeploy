@php
    $adminCount = count($admins);

    $setupPresentation = match ($setupState) {
        'pending' => [
            'label' => 'نیازمند راه‌اندازی',
            'icon' => 'lucide.user-round-plus',
            'iconBackground' => 'bg-warning/10',
            'iconColor' => 'text-warning',
            'statusClasses' =>
                'border-warning/20 bg-warning/10 text-warning',
            'dot' => 'bg-warning',
        ],

        'complete' => [
            'label' => $adminCount . ' مدیر',
            'icon' => 'lucide.users-round',
            'iconBackground' => 'bg-success/10',
            'iconColor' => 'text-success',
            'statusClasses' =>
                'border-success/20 bg-success/10 text-success',
            'dot' => 'bg-success',
        ],

        'unknown' => [
            'label' => 'نامشخص',
            'icon' => 'lucide.circle-help',
            'iconBackground' => 'bg-warning/10',
            'iconColor' => 'text-warning',
            'statusClasses' =>
                'border-warning/20 bg-warning/10 text-warning',
            'dot' => 'bg-warning',
        ],

        default => [
            'label' => null,
            'icon' => 'lucide.users-round',
            'iconBackground' => 'bg-base-200/70',
            'iconColor' => 'text-base-content/45',
            'statusClasses' => null,
            'dot' => null,
        ],
    };
@endphp

<section
    class="overflow-hidden rounded-2xl
           border border-base-300
           bg-base-100"
>
    {{-- Capability header --}}
    <header
        class="flex flex-col gap-4
               px-5 py-5
               sm:flex-row
               sm:items-start
               sm:justify-between
               sm:px-6"
    >
        <div
            class="flex min-w-0
                   items-start gap-3.5"
        >
            <div
                @class([
                    'flex size-10 shrink-0',
                    'items-center justify-center',
                    'rounded-xl',
                    $setupPresentation['iconBackground'],
                    $setupPresentation['iconColor'],
                ])
            >
                <x-icon
                    :name="$setupPresentation['icon']"
                    class="size-4.5"
                />
            </div>

            <div class="min-w-0">
                <div
                    class="flex flex-wrap
                           items-center gap-2"
                >
                    <h3
                        class="text-sm font-semibold
                               text-base-content"
                    >
                        مدیران Marzban
                    </h3>

                    @if ($setupPresentation['label'] !== null)

                        <span
                            class="inline-flex items-center
                                   gap-1.5 rounded-full
                                   border px-2 py-0.5
                                   text-[11px] font-medium
                                   {{ $setupPresentation['statusClasses'] }}"
                        >
                            <span
                                class="size-1.5 rounded-full
                                       {{ $setupPresentation['dot'] }}"
                            ></span>

                            {{ $setupPresentation['label'] }}
                        </span>

                    @endif
                </div>

                <p
                    class="mt-1.5 max-w-2xl
                           text-sm leading-7
                           text-base-content/55"
                >
                    حساب‌های مدیریتی دارای دسترسی به پنل Marzban.
                </p>
            </div>
        </div>
    </header>

    {{-- Pending setup --}}
    @if ($setupState === 'pending')

        <div
            class="border-t border-base-300
                   px-5 py-5
                   sm:px-6"
        >
            <div
                class="flex items-start gap-3
                       rounded-xl
                       border border-info/15
                       bg-info/5
                       px-4 py-3.5"
            >
                <div
                    class="flex size-8 shrink-0
                           items-center justify-center
                           rounded-lg
                           bg-info/10
                           text-info"
                >
                    <x-icon
                        name="lucide.shield-check"
                        class="size-4"
                    />
                </div>

                <p
                    class="text-sm leading-7
                           text-base-content/65"
                >
                    هنوز حساب مدیریتی روی Marzban شناسایی نشده است.
                    این حساب با دسترسی مدیرکل ساخته می‌شود و رمز عبور
                    فقط هنگام اجرای عملیات به سرور ارسال شده و در
                    Coreflare ذخیره نمی‌شود.
                </p>
            </div>

            <x-form
                wire:submit="createAdmin"
                class="mt-5"
                no-separator
            >
                <x-input
                    label="نام کاربری"
                    wire:model.blur="username"
                    icon="lucide.user"
                    hint="۳ تا ۳۲ نویسه؛ حروف کوچک انگلیسی، عدد و زیرخط"
                    dir="ltr"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    wire:loading.attr="disabled"
                    wire:target="createAdmin"
                />

                <div
                    class="grid grid-cols-1 gap-4
                           md:grid-cols-2"
                >
                    <x-input
                        label="رمز عبور"
                        wire:model="password"
                        type="password"
                        icon="lucide.lock-keyhole"
                        hint="حداقل ۸ نویسه"
                        dir="ltr"
                        autocomplete="new-password"
                        wire:loading.attr="disabled"
                        wire:target="createAdmin"
                    />

                    <x-input
                        label="تکرار رمز عبور"
                        wire:model="passwordConfirmation"
                        type="password"
                        icon="lucide.lock-keyhole"
                        dir="ltr"
                        autocomplete="new-password"
                        wire:loading.attr="disabled"
                        wire:target="createAdmin"
                    />
                </div>

                <x-slot:actions>
                    <x-button
                        label="ساخت مدیر Marzban"
                        icon="lucide.user-plus"
                        type="submit"
                        spinner="createAdmin"
                        wire:loading.attr="disabled"
                        wire:target="createAdmin"
                        class="btn-primary btn-sm
                               rounded-xl"
                    />
                </x-slot:actions>
            </x-form>
        </div>

    {{-- Detected administrators --}}
    @elseif ($setupState === 'complete')

        <div class="border-t border-base-300">
            <div
                class="flex items-center
                       gap-2
                       bg-success/[0.025]
                       px-5 py-3
                       text-xs
                       text-base-content/50
                       sm:px-6"
            >
                <x-icon
                    name="lucide.users"
                    class="size-3.5"
                />

                حساب‌های مدیریتی شناسایی‌شده روی این سرور
            </div>

            <div class="divide-y divide-base-300">

                @forelse ($admins as $admin)

                    <div
                        class="flex items-center gap-3
                               px-5 py-3.5
                               sm:px-6"
                    >
                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg
                                   bg-base-200/70
                                   text-base-content/45"
                        >
                            <x-icon
                                name="lucide.user-round"
                                class="size-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                نام کاربری مدیر
                            </p>

                            <p
                                dir="ltr"
                                class="technical-value
                                       mt-0.5 truncate
                                       text-left text-sm
                                       font-medium
                                       text-base-content"
                            >
                                {{ $admin['username'] }}
                            </p>
                        </div>
                    </div>

                @empty

                    <div
                        class="flex items-start gap-3
                               px-5 py-4
                               sm:px-6"
                    >
                        <x-icon
                            name="lucide.circle-help"
                            class="mt-0.5 size-4
                                   shrink-0
                                   text-warning"
                        />

                        <p
                            class="text-sm leading-6
                                   text-base-content/55"
                        >
                            وضعیت مدیران تکمیل‌شده گزارش شده،
                            اما جزئیات حساب‌ها در دسترس نیست.
                            وضعیت مدیریت را دوباره بروزرسانی کنید.
                        </p>
                    </div>

                @endforelse
            </div>
        </div>

    {{-- Unknown --}}
    @elseif ($setupState === 'unknown')

        <div
            class="flex items-start gap-3
                   border-t border-base-300
                   bg-warning/[0.035]
                   px-5 py-4
                   sm:px-6"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-warning/10
                       text-warning"
            >
                <x-icon
                    name="lucide.triangle-alert"
                    class="size-4"
                />
            </div>

            <div>
                <p
                    class="text-sm font-medium
                           text-base-content"
                >
                    وضعیت مدیران قابل تشخیص نیست
                </p>

                <p
                    class="mt-1 text-sm leading-6
                           text-base-content/55"
                >
                    برای جلوگیری از ساخت حساب تکراری،
                    امکان ایجاد مدیر تا دریافت وضعیت معتبر
                    از Marzban غیرفعال است.
                </p>
            </div>
        </div>

    {{-- Not available yet --}}
    @else

        <div
            class="flex items-start gap-3
                   border-t border-base-300
                   bg-base-200/25
                   px-5 py-4
                   sm:px-6"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-base-200
                       text-base-content/40"
            >
                <x-icon
                    name="lucide.clock-3"
                    class="size-4"
                />
            </div>

            <p
                class="text-sm leading-7
                       text-base-content/55"
            >
                مدیریت حساب‌های Marzban پس از نصب و اجرای
                برنامه فعال می‌شود.
            </p>
        </div>

    @endif
</section>
