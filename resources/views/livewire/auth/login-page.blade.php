<div
    class="relative z-10 w-full"
    x-data="{
        passkeySupported: false,
        passkeyLoading: false,
        passkeyError: null,

        init() {
            const refreshSupport = () => {
                this.passkeySupported = Boolean(
                    window.CoreflarePasskeys?.isSupported()
                );
            };

            refreshSupport();

            window.addEventListener(
                'passkeys:ready',
                refreshSupport,
                { once: true },
            );
        },

        async loginWithPasskey() {
            this.passkeyError = null;

            if (! window.CoreflarePasskeys?.isSupported()) {
                this.passkeyError =
                    'مرورگر یا دستگاه شما از Passkey پشتیبانی نمی‌کند.';

                return;
            }

            this.passkeyLoading = true;

            try {
                const response = await window.CoreflarePasskeys.verify({
                    optionsUrl: @js(route('passkey.login-options')),
                    verifyUrl: @js(route('passkey.login')),
                });

                window.location.assign(
                    response?.redirect
                        ?? @js(route('panel.servers.index')),
                );
            } catch (error) {
                this.passkeyError =
                    window.CoreflarePasskeys.messageFor(error);
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

            {{-- Header --}}
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
                    شماره موبایل خود را وارد کنید تا کد تأیید برای شما ارسال شود.
                </p>

            </div>


            {{-- OTP Login --}}
            <form
                wire:submit="sendOtp"
                class="mt-7 space-y-5"
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


            {{-- Passkey Secondary Action --}}
            <div
                class="
                    mt-6
                    border-t border-base-300
                    pt-5
                "
            >
                <div class="text-center">

                    <p
                        class="
                            text-xs leading-6
                            text-base-content/45
                        "
                    >
                        قبلاً Passkey را برای حساب خود فعال کرده‌اید؟
                    </p>

                    <button
                        type="button"
                        x-on:click="loginWithPasskey()"
                        x-bind:disabled="
                            passkeyLoading || ! passkeySupported
                        "
                        class="
                            btn btn-ghost btn-sm
                            mt-1
                            rounded-xl
                            font-medium
                            text-primary
                            disabled:text-base-content/30
                        "
                    >
                        <span
                            x-show="! passkeyLoading"
                            class="inline-flex items-center gap-1.5"
                        >
                            <x-icon
                                name="lucide.fingerprint"
                                class="!size-4 stroke-[1.8]"
                            />

                            ورود با Passkey
                        </span>

                        <span
                            x-show="passkeyLoading"
                            class="loading loading-spinner loading-xs"
                        ></span>
                    </button>

                    <div
                        x-cloak
                        x-show="passkeyError"
                        class="
                            mx-auto mt-2
                            max-w-xs
                            text-xs leading-6
                            text-error
                        "
                        x-text="passkeyError"
                    ></div>

                </div>
            </div>


            {{-- Security Note --}}
            <div
                class="
                    mt-5 flex
                    items-center justify-center
                    gap-1.5
                    text-xs
                    text-base-content/35
                "
            >
                <x-icon
                    name="lucide.shield-check"
                    class="!size-3.5 stroke-[1.6]"
                />

                <span>
                    ورود امن و تأییدشده با شماره موبایل
                </span>
            </div>

        </div>
    </div>
</div>
