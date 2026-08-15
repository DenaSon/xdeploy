<div
    class="relative z-10 w-full"
    x-data="{
        passkeySupported: false,
        passkeyLoading: false,
        passkeyError: null,
        init() {
            const refreshSupport = () => {
                this.passkeySupported = Boolean(window.CoreflarePasskeys?.isSupported());
            };

            refreshSupport();
            window.addEventListener('passkeys:ready', refreshSupport, { once: true });
        },
        async loginWithPasskey() {
            this.passkeyError = null;

            if (! window.CoreflarePasskeys?.isSupported()) {
                this.passkeyError = 'مرورگر یا دستگاه شما از Passkey پشتیبانی نمی‌کند.';
                return;
            }

            this.passkeyLoading = true;

            try {
                const response = await window.CoreflarePasskeys.verify({
                    optionsUrl: @js(route('passkey.login-options')),
                    verifyUrl: @js(route('passkey.login')),
                });

                window.location.assign(
                    response?.redirect ?? @js(route('panel.servers.index')),
                );
            } catch (error) {
                this.passkeyError = window.CoreflarePasskeys.messageFor(error);
            } finally {
                this.passkeyLoading = false;
            }
        },
    }"
>

    <div
        class="
            overflow-hidden
            rounded-2xl
            border border-base-300
            bg-base-100
            shadow-sm
        "
    >
        <div class="p-6 sm:p-8">

            <div class="text-center">
                <div
                    class="
                        mx-auto flex size-11
                        items-center justify-center
                        rounded-xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.log-in"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <h1
                    class="
                        mt-5
                        text-xl font-semibold
                        tracking-tight
                        text-base-content
                    "
                >
                    ورود به {{ config('app.name') }}
                </h1>

                <p
                    class="
                        mx-auto mt-2
                        max-w-xs
                        text-sm leading-7
                        text-base-content/55
                    "
                >
                    با Passkey وارد شوید یا شماره موبایل خود را برای دریافت کد تأیید وارد کنید.
                </p>
            </div>

            <div class="mt-7">
                <button
                    type="button"
                    class="btn btn-outline btn-lg w-full rounded-xl"
                    x-on:click="loginWithPasskey()"
                    x-bind:disabled="passkeyLoading || ! passkeySupported"
                >
                    <span x-show="! passkeyLoading" class="inline-flex items-center gap-2">
                        <x-icon name="lucide.fingerprint" class="!size-5 stroke-[1.8]" />
                        ورود با Passkey
                    </span>

                    <span x-show="passkeyLoading" class="loading loading-spinner loading-sm"></span>
                </button>

                <div
                    x-cloak
                    x-show="passkeyError"
                    class="mt-3 text-center text-xs leading-6 text-error"
                    x-text="passkeyError"
                ></div>
            </div>

            <div class="divider my-6 text-xs text-base-content/35">
                یا با رمز یک‌بار مصرف
            </div>

            <form
                wire:submit="sendOtp"
                class="space-y-5"
            >
                <div>
                    <label
                        for="phone"
                        class="
                            mb-2 block
                            text-sm font-medium
                            text-base-content/75
                        "
                    >
                        شماره موبایل
                    </label>

                    <x-input
                        id="phone"
                        wire:model="phone"
                        placeholder="09xxxxxxxxx"
                        inputmode="numeric"
                        autocomplete="tel"
                        dir="ltr"
                        class="
                            input-lg
                            w-full
                            text-left
                            tracking-wide
                        "
                    />
                </div>

                <x-button
                    spinner="sendOtp"
                    type="submit"
                    label="دریافت کد تأیید"
                    class="
                        btn-primary btn-lg
                        w-full
                        rounded-xl
                        font-medium
                        transition-all
                        duration-200
                    "
                />
            </form>

            <div
                class="
                    mt-6 flex
                    items-center justify-center
                    gap-1.5
                    text-xs
                    text-base-content/40
                "
            >
                <x-icon
                    name="lucide.shield-check"
                    class="!size-4 stroke-[1.6]"
                />

                <span>
                    ورود امن با Passkey یا رمز یک‌بار مصرف
                </span>
            </div>

        </div>
    </div>

</div>
