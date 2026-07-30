<div
    dir="rtl"
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-base-200/50 px-4 py-10 sm:px-6"
>
    {{-- Background decoration --}}
    <div
        class="pointer-events-none absolute -right-40 -top-40 size-[28rem] rounded-full bg-primary/8 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute -bottom-40 -left-40 size-[28rem] rounded-full bg-secondary/8 blur-3xl"
    ></div>

    <div class="relative z-10 w-full max-w-2xl">

        <div
            class="overflow-hidden rounded-3xl border border-base-300/70 bg-base-100/90 shadow-2xl shadow-base-300/30 backdrop-blur-xl"
        >
            {{-- Top accent --}}
            <div class="h-1 bg-gradient-to-l from-primary via-secondary to-primary"></div>

            <div class="p-6 sm:p-8 lg:p-10">

                {{-- Header --}}
                <div class="flex items-start gap-4">

                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/10"
                    >
                        <x-icon
                            name="o-server-stack"
                            class="size-6"
                        />
                    </div>

                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-base-content sm:text-3xl">
                            افزودن سرور
                        </h1>

                        <p class="mt-2 text-sm leading-6 text-base-content/55">
                            سرور VPS خود را به xDeploy متصل کنید.
                        </p>
                    </div>

                </div>

                {{-- Information --}}
                <div
                    role="alert"
                    class="alert alert-info alert-soft mt-7 items-start rounded-2xl border border-info/15"
                >
                    <x-icon
                        name="o-information-circle"
                        class="mt-0.5 size-5 shrink-0"
                    />

                    <div class="text-sm">
                        <p class="font-semibold">
                            اطلاعات اتصال SSH
                        </p>

                        <p class="mt-1 leading-7 opacity-75">
                            اطلاعات دسترسی به سرور را وارد کنید. پیش از ثبت نهایی،
                            می‌توانید اتصال SSH را بررسی کنید.
                        </p>
                    </div>
                </div>

                <div class="divider my-7 opacity-60"></div>

                {{-- Form --}}
                <x-servers.form
                    submit="save"
                    button="افزودن سرور"
                />

            </div>
        </div>

        {{-- Security note --}}
        <div class="mt-5 flex items-center justify-center gap-2 text-xs text-base-content/35">

            <x-icon
                name="o-lock-closed"
                class="size-4"
            />

            <span>
                اطلاعات اتصال شما به‌صورت امن نگهداری می‌شود.
            </span>

        </div>

    </div>
</div>
