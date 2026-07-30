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

        {{-- Edit card --}}
        <div
            class="overflow-hidden rounded-3xl border border-base-300/70 bg-base-100/90 shadow-2xl shadow-base-300/30 backdrop-blur-xl"
        >
            {{-- Top accent --}}
            <div class="h-1 bg-gradient-to-l from-primary via-secondary to-primary"></div>

            <div class="p-6 sm:p-8 lg:p-10">

                {{-- Header --}}
                <div class="flex items-start gap-4">

                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/15"
                    >
                        <x-icon
                            name="o-pencil-square"
                            class="size-6"
                        />
                    </div>

                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-base-content sm:text-3xl">
                            ویرایش سرور
                        </h1>

                        <p class="mt-2 text-sm leading-6 text-base-content/55">
                            اطلاعات اتصال سرور را مشاهده و ویرایش کنید.
                        </p>
                    </div>

                </div>

                {{-- Information --}}
                <div
                    role="alert"
                    class="alert alert-warning alert-soft mt-7 items-start rounded-2xl border border-warning/15"
                >
                    <x-icon
                        name="o-pencil-square"
                        class="mt-0.5 size-5 shrink-0"
                    />

                    <div class="text-sm">
                        <p class="font-semibold">
                            ویرایش اطلاعات اتصال
                        </p>

                        <p class="mt-1 leading-7 opacity-75">
                            تغییرات اطلاعات SSH برای تمام عملیات آینده xDeploy
                            روی این سرور استفاده خواهند شد.
                        </p>
                    </div>
                </div>

                <div class="divider my-7 opacity-60"></div>

                {{-- Form --}}
                <x-servers.form
                    submit="update"
                    button="ذخیره تغییرات"
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
