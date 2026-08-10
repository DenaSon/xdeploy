<section
    class="
        relative
        overflow-hidden
        bg-base-100
    "
>
    {{-- Background atmosphere --}}
    <div
        aria-hidden="true"
        class="
            pointer-events-none
            absolute inset-0
            overflow-hidden
        "
    >
        <div
            class="
                absolute
                start-1/2 top-1/2

                size-[34rem]
                -translate-x-1/2
                -translate-y-1/2

                rounded-full
                bg-primary/[0.07]
                blur-3xl
            "
        ></div>
    </div>


    <div
        class="
            relative

            mx-auto w-full max-w-7xl

            px-4 py-20

            sm:px-6 sm:py-24

            lg:px-8 lg:py-28
        "
    >
        <div
            class="
                relative
                overflow-hidden

                rounded-[1.75rem]

                border border-primary/15
                bg-primary/[0.045]

                px-5 py-12

                text-center

                sm:px-10 sm:py-16

                lg:px-16 lg:py-20
            "
        >

            {{-- Inner atmosphere --}}
            <div
                aria-hidden="true"
                class="
                    pointer-events-none
                    absolute
                    -top-28 start-1/2

                    size-80
                    -translate-x-1/2

                    rounded-full
                    bg-primary/10
                    blur-3xl
                "
            ></div>


            <div
                class="
                    relative

                    mx-auto
                    max-w-2xl
                "
            >

                {{-- Icon --}}
                <div
                    class="
                        mx-auto

                        flex size-12
                        items-center justify-center

                        rounded-2xl

                        bg-primary
                        text-primary-content
                    "
                >
                    <x-icon
                        name="lucide.server-cog"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>


                {{-- Heading --}}
                <h2
                    class="
                        mt-6

                        text-3xl font-semibold
                        leading-[1.4]
                        tracking-tight

                        text-base-content

                        sm:text-4xl
                    "
                >
                    سرورت را به xDeploy بسپار،

                    <span class="text-primary">
                        مدیریت را ساده‌تر کن.
                    </span>
                </h2>


                {{-- Description --}}
                <p
                    class="
                        mx-auto mt-4
                        max-w-xl

                        text-sm leading-7
                        text-base-content/55

                        sm:text-base sm:leading-8
                    "
                >
                    VPS فعلی خودت را متصل کن یا یک سرور جدید تهیه کن؛
                    از آماده‌سازی سرور تا مدیریت برنامه‌ها،
                    xDeploy مسیر را برایت شفاف نگه می‌دارد.
                </p>


                {{-- Actions --}}
                <div
                    class="
                        mt-8

                        flex flex-col
                        items-stretch
                        justify-center
                        gap-3

                        sm:flex-row
                        sm:items-center
                    "
                >
                    <x-button
                        label="شروع با xDeploy"
                        icon="lucide.arrow-left"
                        :link="route('login')"
                        wire:navigate
                        class="
                            btn-primary btn-lg
                            rounded-xl

                            px-7

                            font-medium
                        "
                    />

                    <a
                        href="#how-it-works"
                        class="
                            btn btn-ghost btn-lg
                            rounded-xl

                            px-5

                            font-normal
                            text-base-content/60

                            hover:bg-base-100/70
                            hover:text-base-content
                        "
                    >
                        <x-icon
                            name="lucide.workflow"
                            class="!size-4 stroke-[1.7]"
                        />

                        نحوه کار
                    </a>
                </div>


                {{-- Small reassurance --}}
                <div
                    class="
                        mt-7

                        flex flex-wrap
                        items-center
                        justify-center

                        gap-x-5 gap-y-2

                        text-xs
                        text-base-content/40
                    "
                >
                    <span
                        class="
                            inline-flex
                            items-center gap-1.5
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-3.5 stroke-[1.6]"
                        />

                        VPS خودت
                    </span>

                    <span
                        class="
                            inline-flex
                            items-center gap-1.5
                        "
                    >
                        <x-icon
                            name="lucide.cloud"
                            class="!size-3.5 stroke-[1.6]"
                        />

                        خرید سرور
                    </span>

                    <span
                        class="
                            inline-flex
                            items-center gap-1.5
                        "
                    >
                        <x-icon
                            name="lucide.package"
                            class="!size-3.5 stroke-[1.6]"
                        />

                        برنامه‌های پشتیبانی‌شده
                    </span>
                </div>

            </div>

        </div>
    </div>
</section>
