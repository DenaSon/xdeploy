<div
    dir="rtl"
    class="space-y-6"
>

    {{-- Page header --}}
    <header
        class="
            flex flex-col
            gap-4

            sm:flex-row
            sm:items-end
            sm:justify-between
        "
    >
        <div class="min-w-0">

            <div class="flex items-center gap-3">

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
                        name="lucide.server"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">

                    <div
                        class="
                            flex items-center
                            gap-2
                        "
                    >
                        <h1
                            class="
                                text-2xl font-semibold
                                tracking-tight
                                text-base-content
                            "
                        >
                            سرورها
                        </h1>

                        @if($servers->isNotEmpty())
                            <span
                                class="
                                    inline-flex min-w-6
                                    items-center justify-center

                                    rounded-full
                                    bg-base-300/70

                                    px-2 py-0.5

                                    text-[11px] font-medium
                                    text-base-content/50
                                "
                            >
                                {{ $servers->count() }}
                            </span>
                        @endif
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm leading-6
                            text-base-content/50
                        "
                    >
                        سرورهای متصل به xDeploy را از اینجا مدیریت کن.
                    </p>

                </div>

            </div>

        </div>


        <x-button
            label="افزودن سرور"
            icon="lucide.plus"
            :link="route('panel.servers.create')"
            wire:navigate
            class="
                btn-primary
                rounded-xl

                px-4
                font-medium
            "
        />

    </header>


    {{-- Server list --}}
    @if($servers->isNotEmpty())

        <section
            aria-labelledby="servers-list-title"
            class="space-y-3"
        >

            <h2
                id="servers-list-title"
                class="sr-only"
            >
                فهرست سرورها
            </h2>


            @foreach($servers as $server)

                <article
                    wire:key="server-{{ $server->id }}"
                    class="
                        group

                        rounded-2xl
                        border border-base-300
                        bg-base-100

                        p-4

                        transition-colors
                        duration-200

                        hover:border-primary/20

                        sm:p-5
                    "
                >
                    <div
                        class="
                            flex flex-col
                            gap-4

                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                        "
                    >

                        {{-- Server identity --}}
                        <div
                            class="
                                flex min-w-0
                                items-center gap-3
                            "
                        >

                            {{-- Icon --}}
                            <div
                                class="
                                    flex size-11 shrink-0
                                    items-center justify-center

                                    rounded-xl
                                    bg-base-200

                                    text-base-content/55
                                "
                            >
                                <x-icon
                                    name="lucide.server"
                                    class="!size-5 stroke-[1.7]"
                                />
                            </div>


                            <div class="min-w-0">

                                {{-- Name --}}
                                <div
                                    class="
                                        flex min-w-0
                                        items-center gap-2
                                    "
                                >
                                    <h3
                                        class="
                                            truncate

                                            text-sm font-semibold
                                            tracking-tight
                                            text-base-content

                                            sm:text-base
                                        "
                                    >
                                        {{ $server->name }}
                                    </h3>
                                </div>


                                {{-- Host --}}
                                <div
                                    class="
                                        mt-1.5

                                        flex min-w-0
                                        items-center gap-2

                                        text-xs
                                        text-base-content/45
                                    "
                                >
                                    <x-icon
                                        name="lucide.network"
                                        class="
                                            !size-3.5
                                            shrink-0
                                            stroke-[1.6]
                                        "
                                    />

                                    <span
                                        dir="ltr"
                                        class="
                                            technical-value
                                            truncate
                                            text-base-content/55
                                        "
                                    >
                                        {{ $server->host }}:{{ $server->port }}
                                    </span>
                                </div>

                            </div>

                        </div>


                        {{-- Actions --}}
                        <div
                            class="
                                flex shrink-0
                                items-center gap-1

                                border-t border-base-300/70
                                pt-3

                                sm:border-0
                                sm:pt-0
                            "
                        >

                            {{-- Manage --}}
                            <x-button
                                label="مدیریت"
                                icon="lucide.server-cog"
                                :link="route('panel.servers.dashboard', $server)"
                                wire:navigate
                                :aria-label="'مدیریت سرور ' . $server->name"
                                class="
                                    btn-primary btn-sm

                                    rounded-xl
                                    font-medium
                                "
                            />


                            {{-- Edit --}}
                            <div
                                class="tooltip tooltip-top"
                                data-tip="ویرایش سرور"
                            >
                                <x-button
                                    icon="lucide.pencil"
                                    :link="route('panel.servers.edit', $server)"
                                    wire:navigate
                                    :aria-label="'ویرایش سرور ' . $server->name"
                                    class="
                                        btn-ghost
                                        btn-square
                                        btn-sm

                                        rounded-xl

                                        text-base-content/50

                                        hover:bg-base-200
                                        hover:text-base-content
                                    "
                                />
                            </div>


                            {{-- Delete --}}
                            <div
                                class="tooltip tooltip-top"
                                data-tip="حذف سرور"
                            >
                                <x-button
                                    icon="lucide.trash-2"
                                    wire:click="delete({{ $server->id }})"
                                    wire:confirm="آیا از حذف سرور «{{ $server->name }}» مطمئن هستید؟"
                                    wire:target="delete({{ $server->id }})"
                                    spinner
                                    :aria-label="'حذف سرور ' . $server->name"
                                    class="
                                        btn-ghost
                                        btn-square
                                        btn-sm

                                        rounded-xl

                                        text-error/65

                                        hover:bg-error/10
                                        hover:text-error
                                    "
                                />
                            </div>

                        </div>

                    </div>
                </article>

            @endforeach

        </section>


    @else

        {{-- Empty state --}}
        <section
            class="
                rounded-2xl

                border border-base-300
                bg-base-100

                px-5 py-14

                text-center

                sm:px-8
                sm:py-16
            "
        >
            <div
                class="
                    mx-auto

                    flex size-14
                    items-center justify-center

                    rounded-2xl
                    bg-primary/10
                    text-primary
                "
            >
                <x-icon
                    name="lucide.server"
                    class="!size-6 stroke-[1.7]"
                />
            </div>


            <h2
                class="
                    mt-5

                    text-lg font-semibold
                    tracking-tight
                    text-base-content
                "
            >
                هنوز سروری نداری
            </h2>


            <p
                class="
                    mx-auto mt-2
                    max-w-md

                    text-sm leading-7
                    text-base-content/50
                "
            >
                VPS خودت را به xDeploy متصل کن یا یک سرور جدید تهیه کن
                تا مدیریت سرور و برنامه‌ها را شروع کنی.
            </p>


            <x-button
                label="افزودن اولین سرور"
                icon="lucide.plus"
                :link="route('panel.servers.create')"
                wire:navigate
                class="
                    btn-primary

                    mt-6
                    rounded-xl

                    px-5
                    font-medium
                "
            />

        </section>

    @endif

</div>
