<div class="mx-auto w-full max-w-2xl">

    {{-- Header --}}
    <header class="mb-6">
        <div class="flex items-start gap-3">
            <div
                class="
                    flex size-10 shrink-0
                    items-center justify-center
                    rounded-xl
                    bg-primary/10
                    text-primary
                "
            >
                <x-icon
                    name="lucide.pencil"
                    class="!size-5 stroke-[1.8]"
                />
            </div>

            <div class="min-w-0">
                <h1
                    class="
                        text-2xl font-semibold
                        tracking-tight
                        text-base-content
                    "
                >
                    ویرایش اتصال سرور
                </h1>

                <p
                    class="
                        mt-1.5
                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    اطلاعات SSH مورد استفاده xDeploy برای اتصال
                    به این سرور را ویرایش کنید.
                </p>
            </div>
        </div>
    </header>


    {{-- Form surface --}}
    <section
        class="
            rounded-2xl
            border border-base-300
            bg-base-100
            p-5
            sm:p-6
        "
    >

        {{-- Current server --}}
        <div
            class="
                mb-6
                flex items-center
                justify-between gap-3
                rounded-xl
                bg-base-200/60
                p-4
            "
        >
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="
                        flex size-9 shrink-0
                        items-center justify-center
                        rounded-lg
                        bg-base-100
                        text-base-content/50
                    "
                >
                    <x-icon
                        :name="$server->isUserProvided()
                            ? 'lucide.server'
                            : 'lucide.cloud'"
                        class="!size-4 stroke-[1.7]"
                    />
                </span>

                <div class="min-w-0">
                    <div
                        class="
                            text-[11px]
                            text-base-content/40
                        "
                    >
                        اتصال فعلی
                    </div>

                    <div
                        dir="ltr"
                        class="
                            technical-value
                            mt-1 truncate
                            text-sm font-medium
                            text-base-content/70
                        "
                    >
                        {{ $server->host }}:{{ $server->port }}
                    </div>
                </div>
            </div>

            <span
                @class([
                    '
                        inline-flex shrink-0
                        items-center gap-1.5
                        rounded-full
                        px-2.5 py-1
                        text-[10px] font-medium
                    ',
                    'bg-base-300/60 text-base-content/55' =>
                        $server->isUserProvided(),
                    'bg-primary/10 text-primary' =>
                        ! $server->isUserProvided(),
                ])
            >
                <x-icon
                    :name="$server->isUserProvided()
                        ? 'lucide.link-2'
                        : 'lucide.cloud'"
                    class="!size-3"
                />

                {{ $server->isUserProvided()
                    ? 'VPS شخصی'
                    : 'سرور ابری'
                }}
            </span>
        </div>


        <div
            class="
                mb-5
                flex items-start gap-2.5
                rounded-xl
                border border-info/15
                bg-info/5
                px-3.5 py-3
                text-xs leading-6
                text-base-content/55
            "
        >
            <x-icon
                name="lucide.info"
                class="
                    mt-1 !size-3.5
                    shrink-0
                    text-info
                "
            />

            <span>
                آدرس سرور پس از ثبت ثابت است.
                پورت، نام کاربری و رمز عبور SSH را می‌توانید به‌روزرسانی کنید.
            </span>
        </div>


        <x-servers.form
            submit="update"
            button="ذخیره تغییرات"
            :editing="true"
        />

    </section>


    <div
        class="
            mt-4
            flex items-center
            justify-center gap-1.5
            text-xs
            text-base-content/35
        "
    >
        <x-icon
            name="lucide.lock-keyhole"
            class="!size-3.5 stroke-[1.6]"
        />

        <span>
            اطلاعات اتصال به‌صورت امن نگهداری می‌شود.
        </span>
    </div>

</div>
