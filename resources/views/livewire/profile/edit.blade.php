<div class="mx-auto w-full max-w-2xl space-y-5">
    <header>
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <x-icon
                    name="lucide.user-round"
                    class="!size-5 stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-base-content">
                    پروفایل
                </h1>

                <p class="mt-1.5 text-sm leading-7 text-base-content/50">
                    اطلاعات شخصی حساب خود را تکمیل یا ویرایش کنید. تکمیل پروفایل اختیاری است.
                </p>
            </div>
        </div>
    </header>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-base-200/60 p-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/50">
                <x-icon
                    name="lucide.smartphone"
                    class="!size-4 stroke-[1.7]"
                />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-base-content/40">
                        شماره موبایل حساب
                    </span>

                    <span class="badge badge-success badge-sm gap-1">
                        <x-icon
                            name="lucide.badge-check"
                            class="!size-3 stroke-[1.8]"
                        />
                        تأیید شده
                    </span>
                </div>

                <div
                    dir="ltr"
                    class="mt-1 font-mono text-sm font-medium text-base-content/70"
                >
                    {{ $user->phone }}
                </div>
            </div>
        </div>

        @if($statusMessage || session('profile_status'))
            <div
                role="status"
                class="mb-5 flex items-center gap-2 rounded-xl border border-success/20 bg-success/[0.06] px-3.5 py-3 text-sm text-success"
            >
                <x-icon
                    name="lucide.circle-check"
                    class="!size-4 shrink-0 stroke-[1.8]"
                />

                {{ $statusMessage ?? session('profile_status') }}
            </div>
        @endif

        @if(session('profile_error'))
            <div
                role="alert"
                class="mb-5 flex items-center gap-2 rounded-xl border border-error/20 bg-error/[0.06] px-3.5 py-3 text-sm text-error"
            >
                <x-icon
                    name="lucide.circle-alert"
                    class="!size-4 shrink-0 stroke-[1.8]"
                />

                {{ session('profile_error') }}
            </div>
        @endif

        <form
            wire:submit="save"
            class="space-y-5"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input
                    label="نام"
                    wire:model="firstName"
                    maxlength="80"
                    autocomplete="given-name"
                />

                <x-input
                    label="نام خانوادگی"
                    wire:model="lastName"
                    maxlength="80"
                    autocomplete="family-name"
                />
            </div>

            <div class="space-y-3 rounded-xl border border-base-300 bg-base-200/30 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <x-input
                            label="ایمیل"
                            wire:model="email"
                            type="email"
                            maxlength="254"
                            autocomplete="email"
                            dir="ltr"
                        />
                    </div>

                    @if($user->email_verified_at !== null)
                        <div class="flex h-12 shrink-0 items-center gap-1.5 rounded-xl border border-success/20 bg-success/[0.06] px-3 text-sm font-medium text-success">
                            <x-icon
                                name="lucide.badge-check"
                                class="!size-4 stroke-[1.8]"
                            />
                            تأیید شده
                        </div>
                    @else
                        <a
                            href="{{ route('panel.profile.email.google.redirect') }}"
                            class="btn btn-outline h-12 shrink-0 rounded-xl"
                        >
                            <x-icon
                                name="lucide.badge-check"
                                class="!size-4 stroke-[1.8]"
                            />

                            {{ $user->email ? 'تأیید با Google' : 'افزودن و تأیید با Google' }}
                        </a>
                    @endif
                </div>

                @error('email')
                    <p class="text-xs leading-6 text-error">
                        {{ $message }}
                    </p>
                @enderror

                @if($user->email_verified_at === null)
                    <p class="text-xs leading-6 text-base-content/45">
                        اگر ایمیلی ذخیره کرده‌اید، حساب Google انتخاب‌شده باید همان ایمیل را داشته باشد.
                        اگر ایمیل خالی باشد، ایمیل تأییدشده Google به حساب اضافه می‌شود.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-end border-t border-base-300 pt-5">
                <x-button
                    type="submit"
                    label="ذخیره تغییرات"
                    icon="lucide.check"
                    spinner="save"
                    class="btn-primary rounded-xl"
                />
            </div>
        </form>
    </section>
</div>
