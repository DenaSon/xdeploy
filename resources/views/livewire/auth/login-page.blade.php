<div
    dir="rtl"
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-base-200 px-5 py-12"
>
    {{-- Background decoration --}}
    <div
        class="pointer-events-none absolute -right-32 -top-32 size-96 rounded-full bg-primary/10 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute -bottom-32 -left-32 size-96 rounded-full bg-secondary/10 blur-3xl"
    ></div>

    <div class="relative z-10 w-full max-w-md">

        {{-- Login card --}}
        <div
            class="card overflow-hidden border border-base-300/70 bg-base-100/90 shadow-2xl shadow-base-300/30 backdrop-blur-xl"
        >
            {{-- Top accent --}}
            <div class="h-1 w-full bg-gradient-to-l from-primary via-secondary to-primary"></div>

            <div class="card-body gap-0 p-7 sm:p-10">

                {{-- Brand --}}
                <div class="text-center">

                    <div
                        class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg shadow-primary/20"
                    >
                        <x-icon
                            name="lucide.server"
                            class="size-8"
                        />
                    </div>

                    <h1 class="mt-5 text-3xl font-bold tracking-tight text-base-content">
                        xDeploy
                    </h1>

                    <p
                        dir="ltr"
                        class="mt-1.5 text-sm font-medium tracking-wide text-base-content/45"
                    >
                        Deploy & Manage VPS
                    </p>

                </div>

                <div class="divider my-7 opacity-60"></div>

                {{-- Intro --}}
                <div class="text-center">

                    <h2 class="text-xl font-bold text-base-content">
                        ورود به پنل
                    </h2>

                    <p class="mx-auto mt-2 max-w-xs text-sm leading-7 text-base-content/55">
                        شماره موبایل خود را وارد کنید تا کد تأیید برای شما ارسال شود.
                    </p>

                </div>

                {{-- Form --}}
                <form
                    wire:submit="sendOtp"
                    class="mt-7 space-y-5"
                >
                    <div>
                        <label class="mb-2 block text-sm font-medium text-base-content/75">
                            شماره موبایل
                        </label>

                        <x-input
                            wire:model="phone"
                            icon="o-device-phone-mobile"
                            placeholder="09xxxxxxxxx"
                            inputmode="numeric"
                            autocomplete="tel"
                            dir="ltr"
                            class="input-lg w-full text-left tracking-wider"
                        />
                    </div>

                    <x-button
                        spinner="sendOtp"
                        type="submit"
                        label="ارسال کد تأیید"
                        icon="o-paper-airplane"
                        class="btn-primary btn-lg w-full shadow-lg shadow-primary/20 transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/25"
                    />

                </form>

                {{-- Security note --}}
                <div class="mt-6 flex items-center justify-center gap-2 text-xs text-base-content/40">
                    <x-icon
                        name="o-lock-closed"
                        class="size-4"
                    />

                    <span>ورود امن با رمز یک‌بار مصرف</span>
                </div>

            </div>
        </div>

        {{-- Footer --}}
        <div
            dir="ltr"
            class="mt-6 flex items-center justify-center gap-2 text-xs text-base-content/35"
        >
            <span>{{ config('app.name') }}</span>

            <span class="size-1 rounded-full bg-base-content/20"></span>

            <span>v{{ config('app.version', '1.0.0') }}</span>
        </div>

    </div>
</div>
