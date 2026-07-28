<div class="min-h-screen flex items-center justify-center bg-base-100 px-6">

    <div class="w-full max-w-lg">

        <div class="aura aura-glow">

            <div
                class="card border border-base-300 bg-base-100 shadow-lg transition-all duration-300 hover:shadow-xl">

                <div class="card-body space-y-10 p-8 lg:p-10">

                    {{-- Brand --}}
                    <div class="text-center">

                        <div
                            class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/10">

                            <x-icon
                                name="lucide.server"
                                class="size-8 text-primary"
                            />

                        </div>

                        <h1 class="mt-5 text-3xl font-bold tracking-tight">
                            xDeploy
                        </h1>

                        <p class="mt-2 text-sm text-base-content/60">
                            Deploy & Manage VPS
                        </p>

                    </div>

                    {{-- Intro --}}
                    <div class="space-y-2 text-center">

                        <h2 class="text-xl font-semibold">
                            تأیید شماره موبایل
                        </h2>

                        <p class="mx-auto max-w-sm text-sm leading-7 text-base-content/60">
                            کد تأیید ارسال‌شده به شماره
                            <span class="font-medium text-base-content">
                                {{ $phone }}
                            </span>
                            را وارد کنید.
                        </p>

                    </div>

                    {{-- Form --}}
                    <form
                        wire:submit="verify"
                        class="space-y-8"
                    >

                        <div class="flex justify-center">

                            <label class="otp otp-lg otp-primary">

                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>

                                <input
                                    type="text"
                                    wire:model.defer="code"
                                    autocomplete="one-time-code"
                                    inputmode="numeric"
                                    maxlength="4"
                                    pattern="[0-9]{4}"
                                    required
                                />

                            </label>

                        </div>

                        <x-button
                            spinner
                            type="submit"
                            label="تأیید و ورود"
                            icon="o-check"
                            class="btn btn-primary btn-lg w-full"
                        />

                    </form>

                </div>

            </div>

        </div>

        <div class="mt-6 text-center text-xs tracking-wide text-base-content/40">

            {{ config('app.name') }}

            <span class="mx-2">•</span>

            v{{ config('app.version', '1.0.0') }}

        </div>

    </div>

</div>
