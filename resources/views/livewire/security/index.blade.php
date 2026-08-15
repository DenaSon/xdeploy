<div class="mx-auto w-full max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
            <div class="flex items-center gap-2 text-sm font-medium text-success">
                <span class="flex size-8 items-center justify-center rounded-xl bg-success/10">
                    <x-icon name="lucide.shield-check" class="!size-4 stroke-[1.8]" />
                </span>
                امنیت حساب
            </div>

            <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
                ورود امن، بدون پیچیدگی
            </h1>

            <p class="mt-2 text-sm leading-7 text-base-content/50">
                روش‌های ورود به حساب را مدیریت کنید. شماره موبایل برای ورود و بازیابی باقی می‌ماند و Passkey یک روش سریع‌تر و مقاوم‌تر در برابر فیشینگ است.
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs font-medium text-base-content/45">
            <span class="size-2 rounded-full bg-success"></span>
            شماره موبایل تأیید شده
        </div>
    </header>

    @if (session('admin_passkey_required'))
        <div role="alert" class="flex items-start gap-3 rounded-2xl bg-warning/[0.08] px-4 py-3.5 text-sm text-base-content/70">
            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                <x-icon name="lucide.shield-alert" class="!size-4" />
            </span>

            <div>
                <div class="font-medium text-base-content/85">Passkey برای مدیریت لازم است</div>
                <p class="mt-0.5 text-xs leading-6 text-base-content/50">
                    برای ورود به بخش مدیریت، ابتدا حداقل یک Passkey برای این حساب ثبت کنید.
                </p>
            </div>
        </div>
    @endif

    @if ($statusMessage)
        <div role="status" class="flex items-center gap-2.5 rounded-2xl bg-success/[0.07] px-4 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="!size-4.5 shrink-0" />
            <span>{{ $statusMessage }}</span>
        </div>
    @endif

    @if ($securityError)
        <div role="alert" class="flex items-center gap-2.5 rounded-2xl bg-error/[0.07] px-4 py-3 text-sm text-error">
            <x-icon name="lucide.shield-alert" class="!size-4.5 shrink-0" />
            <span>{{ $securityError }}</span>
        </div>
    @endif

    <section class="rounded-3xl border border-base-300/80 bg-base-100 p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="flex min-w-0 items-center gap-3 rounded-2xl bg-base-200/40 px-4 py-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-100 text-base-content/55 shadow-sm shadow-base-300/30">
                    <x-icon name="lucide.smartphone" class="!size-4.5 stroke-[1.7]" />
                </span>

                <div class="min-w-0">
                    <div class="text-[11px] font-medium text-base-content/40">هویت اصلی</div>
                    <div class="mt-1 truncate font-mono text-sm font-medium text-base-content/80" dir="ltr">
                        {{ $user->phone }}
                    </div>
                </div>

                <span class="mr-auto inline-flex shrink-0 items-center gap-1.5 text-[11px] font-medium text-success">
                    <x-icon name="lucide.circle-check" class="!size-3.5" />
                    تأیید شده
                </span>
            </div>

            <div class="flex min-w-0 items-center gap-3 rounded-2xl bg-base-200/40 px-4 py-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-100 text-base-content/55 shadow-sm shadow-base-300/30">
                    <x-icon name="lucide.fingerprint" class="!size-4.5 stroke-[1.7]" />
                </span>

                <div class="min-w-0">
                    <div class="text-[11px] font-medium text-base-content/40">Passkey</div>
                    <div class="mt-1 text-sm font-medium text-base-content/80">
                        {{ $passkeys->count() > 0 ? $passkeys->count().' مورد فعال' : 'هنوز فعال نشده' }}
                    </div>
                </div>

                @if ($user->isAdmin())
                    <span class="mr-auto shrink-0 rounded-lg bg-warning/10 px-2 py-1 text-[10px] font-medium text-warning">
                        الزامی برای مدیریت
                    </span>
                @endif
            </div>
        </div>
    </section>

    <section
        class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6"
        x-data="{
            supported: false,
            name: '',
            loading: false,
            error: null,
            success: null,
            init() {
                const refreshSupport = () => {
                    this.supported = Boolean(window.CoreflarePasskeys?.isSupported());
                };

                refreshSupport();
                window.addEventListener('passkeys:ready', refreshSupport, { once: true });
            },
            async register() {
                this.error = null;
                this.success = null;

                const name = this.name.trim();

                if (name.length < 2) {
                    this.error = 'یک نام کوتاه برای این Passkey وارد کنید.';
                    return;
                }

                if (! window.CoreflarePasskeys?.isSupported()) {
                    this.error = 'مرورگر یا دستگاه شما از Passkey پشتیبانی نمی‌کند.';
                    return;
                }

                this.loading = true;

                try {
                    await window.CoreflarePasskeys.register({
                        name,
                        optionsUrl: @js(route('panel.security.passkeys.options')),
                        storeUrl: @js(route('panel.security.passkeys.store')),
                    });

                    this.name = '';
                    this.success = 'Passkey با موفقیت به حساب شما اضافه شد.';
                    await $wire.$refresh();
                } catch (error) {
                    this.error = window.CoreflarePasskeys.messageFor(error);
                } finally {
                    this.loading = false;
                }
            },
        }"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3.5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-icon name="lucide.key-round" class="!size-5 stroke-[1.8]" />
                </span>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold">Passkeys</h2>

                        @if ($passkeys->count() > 0)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-success">
                                <span class="size-1.5 rounded-full bg-success"></span>
                                فعال
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/45">
                        با اثر انگشت، تشخیص چهره، Windows Hello، PIN دستگاه یا کلید امنیتی وارد شوید؛ بدون نیاز به دریافت کد یک‌بار مصرف.
                    </p>
                </div>
            </div>

            <span class="shrink-0 text-xs font-medium text-base-content/40">
                {{ $passkeys->count() }} Passkey
            </span>
        </div>

        <div x-cloak x-show="! supported" class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
            <x-icon name="lucide.triangle-alert" class="mt-1 !size-3.5 shrink-0 text-warning" />
            <span>مرورگر فعلی قابلیت Passkey را در دسترس قرار نمی‌دهد.</span>
        </div>

        <div class="mt-6 rounded-2xl bg-base-200/35 p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="form-control min-w-0 flex-1">
                    <span class="mb-2 text-xs font-medium text-base-content/60">نام این دستگاه</span>
                    <input
                        type="text"
                        x-model="name"
                        maxlength="80"
                        autocomplete="off"
                        class="input input-bordered w-full rounded-xl border-base-300 bg-base-100 focus:border-primary/40"
                        placeholder="مثلاً لپ‌تاپ شخصی یا iPhone"
                        x-on:keydown.enter.prevent="register()"
                    >
                </label>

                <button
                    type="button"
                    class="btn btn-primary rounded-xl px-5"
                    x-on:click="register()"
                    x-bind:disabled="loading || ! supported"
                >
                    <span x-show="! loading" class="inline-flex items-center gap-2">
                        <x-icon name="lucide.plus" class="!size-4" />
                        افزودن Passkey
                    </span>
                    <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>

            <p class="mt-2 text-[11px] leading-5 text-base-content/35">
                این نام فقط برای شناسایی Passkey در حساب شما نمایش داده می‌شود.
            </p>

            <div x-cloak x-show="error" class="mt-3 flex items-center gap-2 text-xs text-error">
                <x-icon name="lucide.circle-alert" class="!size-3.5" />
                <span x-text="error"></span>
            </div>

            <div x-cloak x-show="success" class="mt-3 flex items-center gap-2 text-xs text-success">
                <x-icon name="lucide.circle-check" class="!size-3.5" />
                <span x-text="success"></span>
            </div>
        </div>

        <div class="mt-7">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-base-content/45">
                    Passkeyهای ثبت‌شده
                </h3>
            </div>

            <div class="space-y-2.5">
                @forelse ($passkeys as $passkey)
                    @php
                        $isProtectedAdminPasskey = $user->isAdmin() && $passkeys->count() === 1;
                    @endphp

                    <div
                        wire:key="passkey-{{ $passkey->id }}"
                        class="flex flex-col gap-4 rounded-2xl bg-base-200/30 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-100 text-base-content/50 shadow-sm shadow-base-300/20">
                                <x-icon name="lucide.fingerprint" class="!size-4 stroke-[1.7]" />
                            </span>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate text-sm font-medium text-base-content/80">
                                        {{ $passkey->name }}
                                    </div>

                                    @if ($isProtectedAdminPasskey)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning">
                                            <x-icon name="lucide.lock-keyhole" class="!size-2.5" />
                                            محافظت‌شده
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-base-content/35">
                                    @if ($passkey->authenticator)
                                        <span>{{ $passkey->authenticator }}</span>
                                    @endif

                                    <span>افزوده‌شده {{ $passkey->created_at?->format('Y-m-d') }}</span>

                                    @if ($passkey->last_used_at)
                                        <span>آخرین استفاده {{ $passkey->last_used_at->format('Y-m-d H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="deletePasskey({{ $passkey->id }})"
                            wire:confirm="این Passkey حذف شود؟"
                            wire:loading.attr="disabled"
                            wire:target="deletePasskey({{ $passkey->id }})"
                            @disabled($isProtectedAdminPasskey)
                            class="btn btn-ghost btn-sm shrink-0 rounded-xl text-base-content/40 hover:bg-error/[0.06] hover:text-error disabled:text-base-content/25"
                            @if ($isProtectedAdminPasskey) title="برای حساب مدیر باید حداقل یک Passkey باقی بماند." @endif
                        >
                            <x-icon :name="$isProtectedAdminPasskey ? 'lucide.lock-keyhole' : 'lucide.trash-2'" class="!size-3.5" />
                            {{ $isProtectedAdminPasskey ? 'قابل حذف نیست' : 'حذف' }}
                        </button>
                    </div>
                @empty
                    <div class="rounded-2xl bg-base-200/25 px-5 py-9 text-center">
                        <span class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-base-100 text-base-content/30 shadow-sm shadow-base-300/20">
                            <x-icon name="lucide.key-round" class="!size-5 stroke-[1.7]" />
                        </span>

                        <p class="mt-3 text-sm font-medium text-base-content/70">
                            هنوز Passkey ثبت نشده است
                        </p>

                        <p class="mt-1 text-xs leading-6 text-base-content/35">
                            اولین Passkey را برای ورود سریع‌تر و امن‌تر از همین دستگاه اضافه کنید.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-6 flex items-start gap-2.5 px-1 text-[11px] leading-6 text-base-content/40">
            <x-icon name="lucide.shield-check" class="mt-1 !size-3.5 shrink-0 text-success/70" />
            <p>
                شماره موبایل و رمز یک‌بار مصرف همچنان برای ورود جایگزین و بازیابی حساب فعال هستند.
                @if ($user->isAdmin())
                    برای حساب مدیر، تأیید Passkey در session مدیریتی الزامی است و آخرین Passkey از حذف عادی محافظت می‌شود.
                @endif
            </p>
        </div>
    </section>
</div>
