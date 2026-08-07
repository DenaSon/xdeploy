<div
    dir="rtl"
    class="relative flex min-h-screen items-center
           justify-center overflow-hidden
           bg-base-200 px-5 py-12"
>
    {{-- Background decoration --}}
    <div
        class="pointer-events-none absolute -right-32 -top-32
               size-96 rounded-full bg-primary/10 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute -bottom-32 -left-32
               size-96 rounded-full bg-secondary/10 blur-3xl"
    ></div>

    <div class="relative z-10 w-full max-w-md">

        {{-- Verification card --}}
        <div
            class="card overflow-hidden
                   border border-base-300/70
                   bg-base-100/90
                   shadow-2xl shadow-base-300/30
                   backdrop-blur-xl"
        >
            {{-- Top accent --}}
            <div
                class="h-1 w-full bg-gradient-to-l
                       from-primary via-secondary to-primary"
            ></div>

            <div class="card-body gap-0 p-7 sm:p-10">

                {{-- Brand --}}
                <div class="text-center">

                    <div
                        class="mx-auto flex size-16 items-center
                               justify-center rounded-2xl
                               bg-primary text-primary-content
                               shadow-lg shadow-primary/20"
                    >
                        <x-icon
                            name="lucide.server"
                            class="size-8"
                        />
                    </div>

                    <h1
                        class="mt-5 text-3xl font-bold
                               tracking-tight text-base-content"
                    >
                        xDeploy
                    </h1>

                    <p
                        dir="ltr"
                        class="mt-1.5 text-sm font-medium
                               tracking-wide text-base-content/45"
                    >
                        Deploy & Manage VPS
                    </p>

                </div>

                <div class="divider my-7 opacity-60"></div>

                {{-- Intro --}}
                <div class="text-center">

                    <div
                        class="mx-auto mb-4 flex size-11
                               items-center justify-center
                               rounded-full bg-primary/10
                               text-primary"
                    >
                        <x-icon
                            name="o-device-phone-mobile"
                            class="size-5"
                        />
                    </div>

                    <h2
                        class="text-xl font-bold
                               text-base-content"
                    >
                        تأیید شماره موبایل
                    </h2>

                    <p
                        class="mx-auto mt-2 max-w-xs
                               text-sm leading-7
                               text-base-content/55"
                    >
                        کد تأیید ۵ رقمی ارسال‌شده به شماره زیر را وارد کنید.
                    </p>

                    {{-- Phone --}}
                    <div
                        class="mt-4 flex items-center
                               justify-center gap-2"
                    >
                        <div
                            dir="ltr"
                            class="inline-flex items-center
                                   rounded-xl border
                                   border-base-content/5
                                   bg-base-200/70
                                   px-3 py-2
                                   font-mono text-sm
                                   font-semibold tracking-wide
                                   text-base-content/80"
                        >
                            {{ $phone }}
                        </div>

                        {{-- Change phone --}}
                        <button
                            type="button"
                            wire:click="changePhone"
                            wire:loading.attr="disabled"
                            wire:target="changePhone"
                            class="inline-flex items-center gap-1.5
                                   rounded-lg px-2.5 py-2
                                   text-xs font-medium text-primary
                                   transition-colors duration-200
                                   hover:bg-primary/10
                                   disabled:pointer-events-none
                                   disabled:opacity-50"
                        >
                            <x-icon
                                name="lucide.pencil"
                                class="size-3.5"
                            />

                            <span
                                wire:loading.remove
                                wire:target="changePhone"
                            >
                                اصلاح شماره
                            </span>

                            <span
                                wire:loading
                                wire:target="changePhone"
                            >
                                در حال بازگشت...
                            </span>
                        </button>
                    </div>

                </div>

                {{-- Form --}}
                <form
                    wire:submit="verify"
                    class="mt-8 space-y-6"
                >
                    {{-- OTP --}}
                    <div
                        dir="ltr"
                        class="flex justify-center"
                    >
                        <label class="otp otp-lg otp-primary">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>

                            <input
                                type="text"
                                wire:model="code"
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                maxlength="5"
                                pattern="[0-9]{5}"
                                aria-label="کد تأیید پنج رقمی"
                                required
                                autofocus
                            />
                        </label>
                    </div>

                    {{-- Validation error --}}
                    @error('code')
                    <div
                        role="alert"
                        class="alert alert-error
                                   alert-soft rounded-xl
                                   text-sm"
                    >
                        <x-icon
                            name="o-exclamation-circle"
                            class="size-5 shrink-0"
                        />

                        <span>
                                {{ $message }}
                            </span>
                    </div>
                    @enderror

                    {{-- Submit --}}
                    <x-button
                        type="submit"
                        spinner="verify"
                        label="تأیید و ورود"
                        icon="o-check"
                        class="btn-primary btn-lg w-full
                               shadow-lg shadow-primary/20
                               transition duration-300
                               hover:-translate-y-0.5
                               hover:shadow-xl
                               hover:shadow-primary/25"
                    />

                </form>

                {{-- Security note --}}
                <div
                    class="mt-6 flex items-center
                           justify-center gap-2
                           text-xs text-base-content/40"
                >
                    <x-icon
                        name="o-shield-check"
                        class="size-4"
                    />

                    <span>
                        کد تأیید را در اختیار دیگران قرار ندهید
                    </span>
                </div>

            </div>
        </div>

        {{-- Footer --}}
        <div
            dir="ltr"
            class="mt-6 flex items-center
                   justify-center gap-2
                   text-xs text-base-content/35"
        >
            <span>
                {{ config('app.name') }}
            </span>

            <span
                class="size-1 rounded-full
                       bg-base-content/20"
            ></span>

            <span>
                v{{ config('app.version', '1.0.0') }}
            </span>
        </div>

    </div>
</div>
