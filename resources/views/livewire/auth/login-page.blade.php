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
                    ورود به xDeploy
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


            {{-- Form --}}
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
                    label="ادامه"
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

                    text-xs
                    text-base-content/40
                "
            >
                <x-icon
                    name="lucide.shield-check"
                    class="!size-4 stroke-[1.6]"
                />

                <span>
                    ورود امن با رمز یک‌بار مصرف
                </span>
            </div>

        </div>
    </div>

</div>
