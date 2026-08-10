<div class="relative z-10 w-full">

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
                        name="lucide.message-square-code"
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
                    تأیید شماره موبایل
                </h1>

                <p
                    class="
                        mx-auto mt-2
                        max-w-xs

                        text-sm leading-7
                        text-base-content/55
                    "
                >
                    کد تأیید ۵ رقمی ارسال‌شده به شماره زیر را وارد کنید.
                </p>


                {{-- Phone --}}
                <div
                    class="
                        mt-4 flex
                        flex-wrap items-center
                        justify-center gap-1
                    "
                >
                    <span
                        dir="ltr"
                        class="
                            technical-value

                            rounded-lg
                            bg-base-200

                            px-2.5 py-1.5

                            text-sm font-medium
                            text-base-content/75
                        "
                    >
                        {{ $phone }}
                    </span>

                    <button
                        type="button"
                        wire:click="changePhone"
                        wire:loading.attr="disabled"
                        wire:target="changePhone"

                        class="
                            inline-flex
                            items-center gap-1.5

                            rounded-lg
                            px-2 py-1.5

                            text-xs font-medium
                            text-primary

                            transition-colors
                            duration-200

                            hover:bg-primary/10

                            disabled:pointer-events-none
                            disabled:opacity-50
                        "
                    >
                        <x-icon
                            name="lucide.pencil"
                            class="!size-3.5 stroke-[1.7]"
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
                class="mt-7 space-y-5"
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


                {{-- Validation --}}
                @error('code')
                <div
                    role="alert"
                    class="
                            alert alert-error alert-soft
                            rounded-xl

                            text-sm
                        "
                >
                    <x-icon
                        name="lucide.circle-alert"
                        class="!size-5 shrink-0 stroke-[1.7]"
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


            {{-- Security --}}
            <div
                class="
                    mt-6 flex
                    items-center justify-center
                    gap-1.5

                    text-center text-xs
                    text-base-content/40
                "
            >
                <x-icon
                    name="lucide.shield-check"
                    class="!size-4 shrink-0 stroke-[1.6]"
                />

                <span>
                    کد تأیید را در اختیار دیگران قرار ندهید
                </span>
            </div>

        </div>
    </div>

</div>
