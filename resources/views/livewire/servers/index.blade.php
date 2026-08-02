<div dir="rtl" class="space-y-6 sm:space-y-7">

    {{-- Page header --}}
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex items-center gap-4">

            <div
                class="flex size-12 shrink-0 items-center justify-center rounded-2xl
                       bg-gradient-to-br from-primary/15 to-primary/5 text-primary
                       shadow-sm ring-1 ring-primary/15"
            >
                <x-icon
                    name="o-server-stack"
                    class="size-6"
                />
            </div>

            <div class="min-w-0">

                <div class="flex items-center gap-3">

                    <h1 class="text-2xl font-bold tracking-tight text-base-content">
                        سرورها
                    </h1>

                    @if($servers->isNotEmpty())
                        <span class="badge badge-primary badge-soft badge-sm font-semibold">
                            {{ $servers->count() }}
                        </span>
                    @endif

                </div>

                <p class="mt-1 text-sm leading-6 text-base-content/55">
                    سرورهای VPS خود را اضافه و مدیریت کنید.
                </p>

            </div>

        </div>

        <x-button
            label="افزودن سرور"
            icon="o-plus"
            :link="route('panel.servers.create')"
            class="btn-primary rounded-xl shadow-lg shadow-primary/15
                   transition duration-300 hover:-translate-y-0.5
                   hover:shadow-xl hover:shadow-primary/20"
        />

    </div>

    {{-- Server list --}}
    <section
        class="rounded-3xl border border-base-300/70 bg-base-100/90
               shadow-xl shadow-base-300/20 ring-1
               ring-base-content/[0.02] backdrop-blur-sm"
        aria-labelledby="servers-list-title"
    >

        {{-- Card header --}}
        <div
            class="flex items-center justify-between rounded-t-3xl
                   border-b border-base-300/70
                   bg-gradient-to-l from-base-200/60 via-base-100/80 to-base-100
                   px-4 py-4 sm:px-6 sm:py-5"
        >

            <div>

                <h2
                    id="servers-list-title"
                    class="font-bold tracking-tight text-base-content"
                >
                    لیست سرورها
                </h2>

                <p class="mt-1 text-xs text-base-content/45">
                    مدیریت اتصال و تنظیمات سرورهای شما
                </p>

            </div>

            <div
                class="flex size-10 items-center justify-center rounded-xl
                       border border-base-300/60 bg-base-100/80
                       text-base-content/45 shadow-sm"
            >
                <x-icon
                    name="o-circle-stack"
                    class="size-5"
                />
            </div>

        </div>

        @if($servers->isNotEmpty())

            <div class="space-y-2.5 p-3 sm:p-4">

                @foreach($servers as $server)

                    <div
                        wire:key="server-item-{{ $server->id }}"
                        @class([
                            'rounded-2xl border transition-all duration-200',

                            'border-primary/25 bg-primary/[0.06]
                             shadow-sm shadow-primary/5 ring-1 ring-primary/10'
                                => $server->isActive(),

                            'border-base-300/70 bg-base-100
                             hover:border-base-content/10 hover:bg-base-200/40'
                                => ! $server->isActive(),
                        ])
                    >

                        <x-list-item
                            :item="$server"
                            no-separator
                            no-hover
                            class="min-w-0 px-3 py-3 sm:px-4"
                        >

                            {{-- Server icon --}}
                            <x-slot:avatar>

                                <div
                                    @class([
                                        'flex size-11 shrink-0 items-center justify-center
                                         rounded-2xl ring-1 transition-colors duration-200',

                                        'bg-primary/10 text-primary ring-primary/15'
                                            => $server->isActive(),

                                        'bg-base-200 text-base-content/45 ring-base-300/70'
                                            => ! $server->isActive(),
                                    ])
                                >
                                    <x-icon
                                        name="o-server"
                                        class="size-5"
                                    />
                                </div>

                            </x-slot:avatar>

                            {{-- Server name --}}
                            <x-slot:value>

                                <div class="flex min-w-0 items-center gap-2">

                                    <span class="truncate font-bold tracking-tight text-base-content">
                                        {{ $server->name }}
                                    </span>

                                    @if($server->isActive())
                                        <span
                                            class="badge badge-primary badge-soft badge-sm
                                                   hidden shrink-0 gap-1.5 border-primary/15
                                                   font-medium sm:inline-flex"
                                        >
                                            <span
                                                class="size-1.5 rounded-full bg-primary
                                                       ring-2 ring-primary/15"
                                            ></span>

                                            فعال
                                        </span>
                                    @endif

                                </div>

                            </x-slot:value>

                            {{-- Server IP --}}
                            <x-slot:sub-value>

                                <div
                                    class="mt-1 flex min-w-0 items-center gap-2
                                           text-xs text-base-content/50"
                                >

                                    <span
                                        @class([
                                            'size-1.5 shrink-0 rounded-full',

                                            'bg-primary ring-2 ring-primary/15'
                                                => $server->isActive(),

                                            'bg-base-content/20'
                                                => ! $server->isActive(),
                                        ])
                                    ></span>

                                    <span
                                        dir="ltr"
                                        class="truncate font-mono font-medium text-base-content/60"
                                    >
                                        {{ $server->host }}:{{ $server->port }}
                                    </span>

                                    @if($server->isActive())
                                        <span class="shrink-0 font-medium text-primary sm:hidden">
                                            فعال
                                        </span>
                                    @endif

                                </div>

                            </x-slot:sub-value>

                            {{-- Actions --}}
                            <x-slot:actions>

                                <div class="flex shrink-0 items-center gap-0.5 sm:gap-1">

                                    {{-- Manage / Select --}}
                                    <div
                                        class="tooltip tooltip-top
           before:z-50 before:whitespace-nowrap before:text-xs
           after:z-50"
                                        data-tip="مدیریت سرور"
                                    >
                                        <x-button
                                            label="مدیریت"
                                            icon="lucide.server-cog"
                                            :link="route('panel.servers.dashboard', $server)"
                                            :aria-label="'مدیریت سرور ' . $server->name"
                                            class="
            btn-sm gap-2 rounded-xl
            border border-primary/15
            bg-primary/10 text-primary
            shadow-sm shadow-primary/5
            transition-all duration-200
            hover:border-primary/25
            hover:bg-primary/15
        "
                                        />
                                    </div>

                                    {{-- Edit --}}
                                    <div
                                        class="tooltip tooltip-top
                                               before:z-50 before:whitespace-nowrap before:text-xs
                                               after:z-50"
                                        data-tip="ویرایش سرور"
                                    >
                                        <x-button
                                            icon="o-pencil-square"
                                            :link="route('panel.servers.edit', $server)"
                                            aria-label="ویرایش سرور {{ $server->name }}"
                                            class="btn-ghost btn-square btn-xs rounded-xl
                                                   text-base-content/55
                                                   transition-colors duration-200
                                                   hover:bg-primary/10 hover:text-primary"
                                        />
                                    </div>

                                    {{-- Delete --}}
                                    <div
                                        class="tooltip tooltip-top
                                               before:z-50 before:whitespace-nowrap before:text-xs
                                               after:z-50"
                                        data-tip="حذف سرور"
                                    >
                                        <x-button
                                            icon="o-trash"
                                            wire:click="delete({{ $server->id }})"
                                            wire:confirm="آیا از حذف این سرور مطمئن هستید؟"
                                            aria-label="حذف سرور {{ $server->name }}"
                                            spinner
                                            class="btn-ghost btn-square btn-xs rounded-xl
                                                   text-error/70
                                                   transition-colors duration-200
                                                   hover:bg-error/10 hover:text-error"
                                        />
                                    </div>

                                </div>

                            </x-slot:actions>

                        </x-list-item>

                    </div>

                @endforeach

            </div>

        @else

            {{-- Empty state --}}
            <div class="px-6 py-16 text-center sm:py-20">

                <div
                    class="relative mx-auto flex size-20 items-center
                           justify-center rounded-3xl bg-base-200"
                >
                    <div
                        class="absolute inset-0 rounded-3xl
                               bg-primary/5 blur-xl"
                    ></div>

                    <x-icon
                        name="o-server-stack"
                        class="relative size-10 text-base-content/25"
                    />
                </div>

                <h3 class="mt-6 text-lg font-bold text-base-content">
                    هنوز سروری اضافه نکرده‌اید
                </h3>

                <p
                    class="mx-auto mt-2 max-w-sm text-sm
                           leading-7 text-base-content/50"
                >
                    اولین سرور VPS خود را اضافه کنید تا بتوانید وضعیت و سرویس‌های آن را از طریق xDeploy مدیریت کنید.
                </p>

                <x-button
                    label="افزودن اولین سرور"
                    icon="o-plus"
                    :link="route('panel.servers.create')"
                    class="btn-primary mt-7 rounded-xl
                           shadow-lg shadow-primary/15
                           transition duration-300
                           hover:-translate-y-0.5
                           hover:shadow-xl hover:shadow-primary/20"
                />

            </div>

        @endif

    </section>

</div>
