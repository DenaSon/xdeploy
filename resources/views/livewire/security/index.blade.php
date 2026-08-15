<div class="space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm font-medium text-primary">
                <x-icon name="lucide.shield-check" class="!size-4 stroke-[1.8]" />
                امنیت حساب
            </div>

            <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                روش‌های ورود و امنیت
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-7 text-base-content/55">
                می‌توانید با Passkey بدون دریافت کد وارد شوید. شماره موبایل و OTP نیز برای ورود جایگزین و بازیابی دسترسی باقی می‌مانند.
            </p>
        </div>
    </header>

    @if (session('admin_passkey_required'))
        <div role="alert" class="alert alert-warning alert-soft rounded-2xl text-sm">
            <x-icon name="lucide.shield-alert" class="!size-5" />
            <span>
                برای دسترسی به بخش مدیریت، ابتدا حداقل یک Passkey برای حساب مدیر ثبت کنید.
            </span>
        </div>
    @endif

    @if ($statusMessage)
        <div role="status" class="alert alert-success alert-soft rounded-2xl text-sm">
            <x-icon name="lucide.circle-check" class="!size-5" />
            <span>{{ $statusMessage }}</span>
        </div>
    @endif

    @if ($securityError)
        <div role="alert" class="alert alert-error alert-soft rounded-2xl text-sm">
            <x-icon name="lucide.shield-alert" class="!size-5" />
            <span>{{ $securityError }}</span>
        </div>
    @endif

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                <x-icon name="lucide.smartphone" class="!size-5 stroke-[1.8]" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold">شماره موبایل</h2>
                    <span class="badge badge-success badge-sm">تأیید شده</span>
                </div>

                <div class="mt-2 font-mono text-sm text-base-content/75" dir="ltr">
                    {{ $user->phone }}
                </div>

                <p class="mt-2 text-xs leading-6 text-base-content/45">
                    این شماره برای ورود با رمز یک‌بار مصرف و بازیابی دسترسی حساب استفاده می‌شود.
                </p>
            </div>
        </div>
    </section>

    <section
        class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
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
        <div class="flex flex-col gap-4 border-b border-base-300 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-icon name="lucide.key-round" class="!size-5 stroke-[1.8]" />
                </span>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold">Passkeys</h2>

                        @if ($user->isAdmin())
                            <span class="badge badge-warning badge-sm">برای مدیریت الزامی</span>
                        @endif
                    </div>

                    <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/50">
                        Passkey با Windows Hello، اثر انگشت، تشخیص چهره، PIN دستگاه یا کلید امنیتی تأیید می‌شود و برای ورود بدون OTP قابل استفاده است.
                    </p>
                </div>
            </div>

            <span class="badge badge-ghost shrink-0">
                {{ $passkeys->count() }} Passkey
            </span>
        </div>

        <div class="space-y-5 p-5 sm:p-6">
            <div x-cloak x-show="! supported" class="alert alert-warning alert-soft rounded-xl text-sm">
                <x-icon name="lucide.triangle-alert" class="!size-5" />
                <span>مرورگر فعلی قابلیت Passkey را در دسترس قرار نمی‌دهد.</span>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-200/25 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="form-control min-w-0 flex-1">
                        <span class="mb-2 text-sm font-medium">نام Passkey</span>
                        <input
                            type="text"
                            x-model="name"
                            maxlength="80"
                            autocomplete="off"
                            class="input input-bordered w-full rounded-xl"
                            placeholder="مثلاً لپ‌تاپ شخصی یا iPhone"
                            x-on:keydown.enter.prevent="register()"
                        >
                    </label>

                    <button
                        type="button"
                        class="btn btn-primary rounded-xl"
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

                <p class="mt-2 text-xs leading-6 text-base-content/40">
                    نام فقط برای تشخیص دستگاه در پنل استفاده می‌شود و می‌تواند چیزی مثل «لپ‌تاپ شخصی» باشد.
                </p>

                <div x-cloak x-show="error" class="mt-3 text-xs text-error" x-text="error"></div>
                <div x-cloak x-show="success" class="mt-3 text-xs text-success" x-text="success"></div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Passkeyهای ثبت‌شده</h3>
                </div>

                <div class="divide-y divide-base-300 overflow-hidden rounded-2xl border border-base-300">
                    @forelse ($passkeys as $passkey)
                        @php
                            $isProtectedAdminPasskey = $user->isAdmin() && $passkeys->count() === 1;
                        @endphp

                        <div
                            wire:key="passkey-{{ $passkey->id }}"
                            class="flex flex-col gap-4 bg-base-100 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/55">
                                    <x-icon name="lucide.fingerprint" class="!size-4.5 stroke-[1.7]" />
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="truncate text-sm font-medium">{{ $passkey->name }}</div>

                                        @if ($isProtectedAdminPasskey)
                                            <span class="badge badge-warning badge-outline badge-xs">محافظت‌شده</span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/40">
                                        @if ($passkey->authenticator)
                                            <span>{{ $passkey->authenticator }}</span>
                                        @endif

                                        <span>افزوده‌شده: {{ $passkey->created_at?->format('Y-m-d') }}</span>

                                        @if ($passkey->last_used_at)
                                            <span>آخرین استفاده: {{ $passkey->last_used_at->format('Y-m-d H:i') }}</span>
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
                                class="btn btn-ghost btn-sm shrink-0 rounded-xl text-error disabled:text-base-content/30"
                                @if ($isProtectedAdminPasskey) title="برای حساب مدیر باید حداقل یک Passkey باقی بماند." @endif
                            >
                                <x-icon :name="$isProtectedAdminPasskey ? 'lucide.lock-keyhole' : 'lucide.trash-2'" class="!size-4" />
                                {{ $isProtectedAdminPasskey ? 'آخرین Passkey' : 'حذف' }}
                            </button>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-base-200 text-base-content/35">
                                <x-icon name="lucide.key-round" class="!size-5 stroke-[1.7]" />
                            </span>
                            <p class="mt-3 text-sm font-medium">هنوز Passkey ثبت نشده است.</p>
                            <p class="mt-1 text-xs leading-6 text-base-content/40">
                                اولین Passkey را از همین دستگاه اضافه کنید.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex items-start gap-2 rounded-xl bg-info/[0.05] px-4 py-3 text-xs leading-6 text-base-content/55">
                <x-icon name="lucide.info" class="mt-1 !size-3.5 shrink-0 text-info" />
                <p>
                    Passkey اکنون برای ورود بدون OTP فعال است. ورود با شماره موبایل و رمز یک‌بار مصرف همچنان به‌عنوان روش جایگزین باقی می‌ماند.
                    @if ($user->isAdmin())
                        دسترسی به بخش مدیریت علاوه بر ورود حساب، به تأیید Passkey در session مدیریتی نیز نیاز دارد و آخرین Passkey مدیر از حذف عادی محافظت می‌شود.
                    @endif
                </p>
            </div>
        </div>
    </section>
</div>
