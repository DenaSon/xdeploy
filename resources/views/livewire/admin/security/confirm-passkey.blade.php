<div
    class="mx-auto max-w-xl"
    x-data="{
        supported: false,
        loading: false,
        error: null,
        init() {
            const refreshSupport = () => {
                this.supported = Boolean(window.CoreflarePasskeys?.isSupported());
            };

            refreshSupport();
            window.addEventListener('passkeys:ready', refreshSupport, { once: true });
        },
        async confirm() {
            this.error = null;

            if (! window.CoreflarePasskeys?.isSupported()) {
                this.error = 'مرورگر یا دستگاه شما از Passkey پشتیبانی نمی‌کند.';
                return;
            }

            this.loading = true;

            try {
                const response = await window.CoreflarePasskeys.verify({
                    optionsUrl: @js(route('admin.passkey.options')),
                    verifyUrl: @js(route('admin.passkey.verify')),
                });

                window.location.assign(
                    response?.redirect ?? @js(route('admin.dashboard')),
                );
            } catch (error) {
                this.error = window.CoreflarePasskeys.messageFor(error);
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
        <div class="p-6 sm:p-8">
            <div class="text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-icon name="lucide.fingerprint" class="!size-6 stroke-[1.8]" />
                </span>

                <h1 class="mt-5 text-xl font-semibold tracking-tight">
                    تأیید دسترسی مدیریت
                </h1>

                <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-base-content/55">
                    برای ورود به بخش مدیریت، هویت خود را با یکی از Passkeyهای ثبت‌شده این حساب تأیید کنید.
                </p>
            </div>

            <div
                x-cloak
                x-show="! supported"
                class="alert alert-warning alert-soft mt-6 rounded-xl text-sm"
            >
                <x-icon name="lucide.triangle-alert" class="!size-5" />
                <span>مرورگر فعلی قابلیت Passkey را در دسترس قرار نمی‌دهد.</span>
            </div>

            <div
                x-cloak
                x-show="error"
                class="alert alert-error alert-soft mt-6 rounded-xl text-sm"
            >
                <x-icon name="lucide.circle-alert" class="!size-5" />
                <span x-text="error"></span>
            </div>

            <button
                type="button"
                class="btn btn-primary btn-lg mt-7 w-full rounded-xl"
                x-on:click="confirm()"
                x-bind:disabled="loading || ! supported"
            >
                <span x-show="! loading" class="inline-flex items-center gap-2">
                    <x-icon name="lucide.fingerprint" class="!size-5" />
                    تأیید با Passkey
                </span>

                <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            </button>

            <div class="mt-5 flex items-start gap-2 rounded-xl bg-base-200/50 px-4 py-3 text-xs leading-6 text-base-content/50">
                <x-icon name="lucide.shield-check" class="mt-1 !size-3.5 shrink-0 text-success" />
                <p>
                    این تأیید فقط برای session مدیریتی فعلی معتبر است و پس از پایان بازه امنیتی دوباره درخواست می‌شود.
                </p>
            </div>
        </div>
    </section>
</div>
