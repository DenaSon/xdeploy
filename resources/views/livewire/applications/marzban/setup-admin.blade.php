<div>
    <x-card
        class="border border-base-300 bg-base-100 shadow-sm"
    >
        <div
            class="flex flex-col gap-4
                   sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex items-start gap-4">

                <div
                    @class([
                        'flex size-11 shrink-0 items-center justify-center rounded-2xl',
                        'bg-warning/10' => $setupState === 'pending',
                        'bg-success/10' => $setupState === 'complete',
                        'bg-base-200' => ! in_array(
                            $setupState,
                            ['pending', 'complete'],
                            true,
                        ),
                    ])
                >
                    <x-icon
                        name="o-user-circle"
                        @class([
                            'size-6',
                            'text-warning' => $setupState === 'pending',
                            'text-success' => $setupState === 'complete',
                            'text-base-content/40' => ! in_array(
                                $setupState,
                                ['pending', 'complete'],
                                true,
                            ),
                        ])
                    />
                </div>

                <div>
                    <h3 class="font-semibold text-base-content">
                        مدیر اصلی Marzban
                    </h3>

                    <p
                        class="mt-1 text-sm leading-7
                               text-base-content/60"
                    >
                        حساب مدیرکل برای ورود و مدیریت پنل Marzban.
                    </p>
                </div>

            </div>

            @if ($setupState === 'pending')

                <span
                    class="badge badge-warning badge-outline gap-1.5"
                >
                    <x-icon
                        name="o-clock"
                        class="size-3.5"
                    />

                    نیازمند راه‌اندازی
                </span>

            @elseif ($setupState === 'complete')

                <span
                    class="badge badge-success badge-outline gap-1.5"
                >
                    <x-icon
                        name="o-check-circle"
                        class="size-3.5"
                    />

                    تکمیل‌شده
                </span>

            @elseif ($setupState === 'unknown')

                <span
                    class="badge badge-warning badge-outline gap-1.5"
                >
                    <x-icon
                        name="o-question-mark-circle"
                        class="size-3.5"
                    />

                    نامشخص
                </span>

            @endif
        </div>

        @if ($setupState === 'pending')

            <div
                class="mt-6 flex items-start gap-3 rounded-2xl
                       border border-info/15 bg-info/5 p-4"
            >
                <x-icon
                    name="o-information-circle"
                    class="mt-0.5 size-5 shrink-0 text-info"
                />

                <p class="text-sm leading-7 text-base-content/70">
                    این حساب با دسترسی مدیرکل ساخته می‌شود. رمز عبور فقط
                    هنگام اجرای عملیات به سرور ارسال شده و ذخیره نمی‌شود.
                </p>
            </div>

            <x-form
                wire:submit="createAdmin"
                class="mt-6"
                no-separator
            >
                <x-input
                    label="نام کاربری"
                    wire:model.blur="username"
                    icon="o-user"
                    hint="۳ تا ۳۲ نویسه؛ حروف کوچک انگلیسی، عدد و زیرخط"
                    dir="ltr"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    wire:loading.attr="disabled"
                    wire:target="createAdmin"
                />

                <div
                    class="grid grid-cols-1 gap-5
                           md:grid-cols-2"
                >
                    <x-input
                        label="رمز عبور"
                        wire:model="password"
                        type="password"
                        icon="o-lock-closed"
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
                        icon="o-lock-closed"
                        dir="ltr"
                        autocomplete="new-password"
                        wire:loading.attr="disabled"
                        wire:target="createAdmin"
                    />
                </div>

                <x-slot:actions>
                    <x-button
                        label="ساخت مدیر Marzban"
                        icon="o-user-plus"
                        type="submit"
                        spinner="createAdmin"
                        class="btn-primary"
                    />
                </x-slot:actions>
            </x-form>

        @elseif ($setupState === 'complete')

            <div
                class="mt-6 flex items-start gap-3 rounded-2xl
                       border border-success/15 bg-success/5 p-4"
            >
                <x-icon
                    name="o-check-circle"
                    class="mt-0.5 size-5 shrink-0 text-success"
                />

                <div>
                    <p class="text-sm font-medium text-success">
                        مدیر Marzban آماده استفاده است
                    </p>

                    <p
                        class="mt-1 text-sm leading-7
                               text-base-content/60"
                    >
                        حساب مدیرکل ساخته و با موفقیت روی سرور تأیید شده است.
                    </p>
                </div>
            </div>

        @elseif ($setupState === 'unknown')

            <div
                class="mt-6 flex items-start gap-3 rounded-2xl
                       border border-warning/15 bg-warning/5 p-4"
            >
                <x-icon
                    name="o-exclamation-triangle"
                    class="mt-0.5 size-5 shrink-0 text-warning"
                />

                <p class="text-sm leading-7 text-base-content/70">
                    وضعیت مدیر Marzban قابل تشخیص نیست. برای جلوگیری از ساخت
                    حساب تکراری، فرم تا مشخص‌شدن وضعیت غیرفعال است.
                </p>
            </div>

        @else

            <div
                class="mt-6 rounded-2xl border border-base-300
                       bg-base-200/30 p-4"
            >
                <p class="text-sm leading-7 text-base-content/60">
                    ساخت مدیر پس از نصب و اجرای Marzban فعال می‌شود.
                </p>
            </div>

        @endif
    </x-card>
</div>
