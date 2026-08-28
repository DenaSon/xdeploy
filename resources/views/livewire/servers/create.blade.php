<div>
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
                    name="lucide.server-plus"
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
                    اتصال VPS
                </h1>

                <p
                    class="
                    mt-1.5
                    text-sm leading-7
                    text-base-content/50
                "
                >
                    اطلاعات SSH سرور را وارد کن و پس از تأیید اتصال،
                    آن را به Coreflare اضافه کن.
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

        {{-- Connection requirement --}}
        <div
            class="
            mb-6

            flex items-start gap-3

            rounded-xl
            bg-base-200/60

            p-4
        "
        >
        <span
            class="
                flex size-8 shrink-0
                items-center justify-center

                rounded-lg
                bg-base-100
                text-base-content/45
            "
        >
            <x-icon
                name="lucide.shield-check"
                class="!size-4 stroke-[1.7]"
            />
        </span>

            <div class="min-w-0">

                <div
                    class="
                    text-xs font-medium
                    text-base-content/65
                "
                >
                    تأیید اتصال الزامی است
                </div>



            </div>

        </div>


        {{-- Shared form --}}
        <x-servers.form
            submit="save"
            button="افزودن سرور"
            :require-verified-connection="true"
            :connection-verified="$this->connectionIsVerified()"
            :authentication-type="$authenticationType"
        />

    </section>


    {{-- Security note --}}
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


    {{-- Alternative path --}}
    <div
        class="
        my-7

        flex items-center gap-3

        text-xs
        text-base-content/35
    "
        aria-hidden="true"
    >
        <span class="h-px flex-1 bg-base-300"></span>

        <span>
        VPS نداری؟
    </span>

        <span class="h-px flex-1 bg-base-300"></span>
    </div>



    {{-- Buy VPS CTA --}}
    @if (! auth()->user()->servers()->exists())
        <x-servers.buy-vps-cta
            :link="route('panel.servers.buy')"
        />
    @endif

</div>
