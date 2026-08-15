<div
    dir="rtl"
    class="mx-auto w-full max-w-6xl space-y-6"
>
    {{-- Page header --}}
    <header
        class="
            flex flex-col gap-4
            sm:flex-row sm:items-end sm:justify-between
        "
    >
        <div class="min-w-0">
            <div class="flex items-start gap-3">
                <span
                    class="
                        flex size-10 shrink-0 items-center justify-center
                        rounded-xl bg-primary/10 text-primary
                        ring-1 ring-primary/10
                    "
                >
                    <x-icon
                        name="lucide.server"
                        class="!size-[18px] stroke-[1.8]"
                    />
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <h1
                            class="
                                text-xl font-semibold tracking-tight
                                text-base-content sm:text-2xl
                            "
                        >
                            سرورها
                        </h1>

                        @if($servers->isNotEmpty())
                            <span class="text-[11px] font-medium text-base-content/35">
                                {{ $servers->count() }} سرور
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-xs leading-6 text-base-content/45 sm:text-sm">
                        سرورهای متصل و خریداری‌شده را از یکجا مدیریت کنید.
                    </p>
                </div>
            </div>
        </div>

        {{-- Primary actions --}}
        <div
            class="
                grid w-full grid-cols-2 gap-2
                sm:flex sm:w-auto sm:items-center
            "
        >
            <x-button
                label="اتصال سرور"
                icon="lucide.link-2"
                :link="route('panel.servers.create')"
                wire:navigate
                class="
                    btn-ghost btn-sm
                    min-h-10 rounded-xl border border-base-300/80
                    px-3 font-medium text-base-content/65
                    hover:border-primary/20 hover:bg-base-200/60 hover:text-base-content
                    sm:px-4
                "
            />

            <x-button
                label="خرید VPS"
                icon="lucide.cloud"
                :link="route('panel.servers.buy')"
                wire:navigate
                class="
                    btn-primary btn-sm
                    min-h-10 rounded-xl px-3 font-medium
                    shadow-sm shadow-primary/10
                    sm:px-4
                "
            />
        </div>
    </header>

    @if($servers->isNotEmpty())
        <section
            aria-labelledby="servers-list-title"
            class="space-y-2.5"
        >
            <h2 id="servers-list-title" class="sr-only">
                فهرست سرورها
            </h2>

            @foreach($servers as $server)
                <article
                    wire:key="server-{{ $server->id }}"
                    x-data="{ actionsOpen: false }"
                    @keydown.escape.window="actionsOpen = false"
                    class="
                        group relative overflow-visible
                        rounded-2xl border border-base-300/70
                        bg-base-100 p-3.5
                        transition duration-200
                        hover:border-primary/20
                        hover:shadow-md hover:shadow-base-content/[0.025]
                        sm:p-4
                    "
                >
                    <div
                        class="
                            flex flex-col gap-3
                            sm:flex-row sm:items-center sm:justify-between
                        "
                    >
                        {{-- Server identity --}}
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                @class([
                                    '
                                        flex size-11 shrink-0 items-center justify-center
                                        rounded-xl ring-1
                                        transition-colors duration-200
                                    ',
                                    '
                                        bg-base-200/55 text-base-content/45
                                        ring-base-300/60
                                        group-hover:bg-base-200/80
                                    ' => $server->isUserProvided(),
                                    '
                                        bg-primary/[0.08] text-primary
                                        ring-primary/10
                                        group-hover:bg-primary/[0.11]
                                    ' => ! $server->isUserProvided(),
                                ])
                            >
                                <x-icon
                                    :name="$server->isUserProvided()
                                        ? 'lucide.server'
                                        : 'lucide.cloud'"
                                    class="!size-[18px] stroke-[1.7]"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1.5">
                                    <h3
                                        class="
                                            max-w-56 truncate
                                            text-sm font-semibold tracking-tight
                                            text-base-content
                                            sm:max-w-80 sm:text-[15px]
                                        "
                                    >
                                        {{ $server->name }}
                                    </h3>

                                    <span
                                        @class([
                                            '
                                                inline-flex shrink-0 items-center gap-1.5
                                                text-[10px] font-medium
                                            ',
                                            'text-success' => $server->isActive(),
                                            'text-base-content/35' => ! $server->isActive(),
                                        ])
                                    >
                                        <span
                                            @class([
                                                'size-1.5 rounded-full',
                                                'bg-success' => $server->isActive(),
                                                'bg-base-content/25' => ! $server->isActive(),
                                            ])
                                        ></span>

                                        {{ $server->isActive() ? 'آماده' : 'غیرفعال' }}
                                    </span>
                                </div>

                                <div
                                    class="
                                        mt-1.5 flex min-w-0 flex-wrap items-center
                                        gap-x-2 gap-y-1
                                        text-[11px] text-base-content/38
                                    "
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon
                                            :name="$server->isUserProvided()
                                                ? 'lucide.link-2'
                                                : 'lucide.cloud'"
                                            class="!size-3 stroke-[1.7]"
                                        />

                                        {{ $server->isUserProvided() ? 'متصل' : 'ابری' }}
                                    </span>

                                    <span aria-hidden="true" class="text-base-content/15">•</span>

                                    <span class="inline-flex min-w-0 items-center gap-1.5">
                                        <x-icon
                                            name="lucide.network"
                                            class="!size-3 shrink-0 stroke-[1.7]"
                                        />

                                        <span
                                            dir="ltr"
                                            class="technical-value truncate text-base-content/50"
                                        >
                                            {{ $server->host }}:{{ $server->port }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Desktop actions --}}
                        <div class="hidden shrink-0 items-center gap-1 sm:flex">
                            <x-button
                                label="داشبورد"
                                icon="lucide.arrow-left"
                                :link="route('panel.servers.dashboard', $server)"
                                wire:navigate
                                :aria-label="'باز کردن داشبورد سرور ' . $server->name"
                                class="
                                    btn-primary btn-sm min-h-9 rounded-xl
                                    px-3.5 font-medium
                                    shadow-sm shadow-primary/10
                                "
                            />

                            <div class="tooltip tooltip-top" data-tip="تنظیمات اتصال">
                                <x-button
                                    icon="lucide.pencil"
                                    :link="route('panel.servers.edit', $server)"
                                    wire:navigate
                                    :aria-label="'ویرایش اتصال سرور ' . $server->name"
                                    class="
                                        btn-ghost btn-square btn-sm min-h-9
                                        rounded-xl text-base-content/35
                                        hover:bg-base-200/70 hover:text-base-content
                                    "
                                />
                            </div>

                            @if($server->isUserProvided())
                                <div class="tooltip tooltip-top" data-tip="حذف سرور">
                                    <x-button
                                        icon="lucide.trash-2"
                                        wire:click="delete({{ $server->id }})"
                                        wire:confirm="آیا از حذف سرور «{{ $server->name }}» مطمئن هستید؟"
                                        wire:target="delete({{ $server->id }})"
                                        spinner
                                        :aria-label="'حذف سرور ' . $server->name"
                                        class="
                                            btn-ghost btn-square btn-sm min-h-9
                                            rounded-xl text-error/45
                                            hover:bg-error/[0.07] hover:text-error
                                        "
                                    />
                                </div>
                            @endif
                        </div>

                        {{-- Mobile actions --}}
                        <div class="relative flex items-center gap-2 sm:hidden">
                            <x-button
                                label="داشبورد"
                                icon="lucide.arrow-left"
                                :link="route('panel.servers.dashboard', $server)"
                                wire:navigate
                                :aria-label="'باز کردن داشبورد سرور ' . $server->name"
                                class="
                                    btn-primary btn-sm min-h-9 flex-1
                                    rounded-xl font-medium
                                "
                            />

                            <button
                                type="button"
                                @click="actionsOpen = ! actionsOpen"
                                :aria-expanded="actionsOpen.toString()"
                                aria-label="گزینه‌های بیشتر"
                                class="
                                    btn btn-square btn-ghost btn-sm min-h-9
                                    rounded-xl border border-base-300/70
                                    text-base-content/45
                                    hover:bg-base-200/70 hover:text-base-content
                                "
                            >
                                <x-icon
                                    name="lucide.ellipsis"
                                    class="!size-4 stroke-[1.8]"
                                />
                            </button>

                            <div
                                x-cloak
                                x-show="actionsOpen"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.outside="actionsOpen = false"
                                class="
                                    absolute bottom-11 end-0 z-20 w-48
                                    origin-bottom-left overflow-hidden rounded-xl
                                    border border-base-300/80 bg-base-100/95 p-1.5
                                    shadow-xl shadow-base-content/[0.07]
                                    backdrop-blur-xl
                                "
                            >
                                <a
                                    href="{{ route('panel.servers.edit', $server) }}"
                                    wire:navigate
                                    @click="actionsOpen = false"
                                    class="
                                        flex w-full items-center gap-2.5 rounded-lg
                                        px-3 py-2.5 text-xs font-medium
                                        text-base-content/65
                                        transition-colors duration-150
                                        hover:bg-base-200 hover:text-base-content
                                    "
                                >
                                    <x-icon
                                        name="lucide.pencil"
                                        class="!size-4 stroke-[1.7]"
                                    />

                                    تنظیمات اتصال
                                </a>

                                @if($server->isUserProvided())
                                    <div class="my-1 h-px bg-base-300/70"></div>

                                    <button
                                        type="button"
                                        wire:click="delete({{ $server->id }})"
                                        wire:confirm="آیا از حذف سرور «{{ $server->name }}» مطمئن هستید؟"
                                        wire:target="delete({{ $server->id }})"
                                        @click="actionsOpen = false"
                                        class="
                                            flex w-full items-center gap-2.5 rounded-lg
                                            px-3 py-2.5 text-xs font-medium
                                            text-error/70
                                            transition-colors duration-150
                                            hover:bg-error/10 hover:text-error
                                        "
                                    >
                                        <x-icon
                                            name="lucide.trash-2"
                                            class="!size-4 stroke-[1.7]"
                                        />

                                        حذف سرور
                                    </button>
                                @endif
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
                relative overflow-hidden rounded-2xl
                border border-base-300/70 bg-base-100
                px-5 py-12 text-center sm:px-8 sm:py-14
            "
        >
            <div
                aria-hidden="true"
                class="
                    pointer-events-none absolute start-1/2 top-8
                    size-64 -translate-x-1/2 rounded-full
                    bg-primary/[0.05] blur-3xl
                "
            ></div>

            <div class="relative mx-auto max-w-md">
                <span
                    class="
                        mx-auto flex size-12 items-center justify-center
                        rounded-2xl bg-primary/10 text-primary
                        ring-1 ring-primary/10
                    "
                >
                    <x-icon
                        name="lucide.server-plus"
                        class="!size-5 stroke-[1.7]"
                    />
                </span>

                <h2 class="mt-4 text-base font-semibold tracking-tight text-base-content">
                    هنوز سروری ندارید
                </h2>

                <p class="mx-auto mt-1.5 max-w-sm text-xs leading-6 text-base-content/45 sm:text-sm">
                    سرور فعلی خود را متصل کنید یا یک VPS جدید تهیه کنید و مدیریت را شروع کنید.
                </p>

                <div
                    class="
                        mt-6 grid grid-cols-2 gap-2
                        sm:flex sm:items-center sm:justify-center
                    "
                >
                    <x-button
                        label="اتصال سرور"
                        icon="lucide.link-2"
                        :link="route('panel.servers.create')"
                        wire:navigate
                        class="
                            btn-ghost btn-sm min-h-10 rounded-xl
                            border border-base-300/80 px-4 font-medium
                        "
                    />

                    <x-button
                        label="خرید VPS"
                        icon="lucide.cloud"
                        :link="route('panel.servers.buy')"
                        wire:navigate
                        class="
                            btn-primary btn-sm min-h-10 rounded-xl
                            px-4 font-medium shadow-sm shadow-primary/10
                        "
                    />
                </div>

                <div
                    class="
                        mt-5 flex flex-wrap items-center justify-center
                        gap-x-3 gap-y-1.5
                        text-[10px] text-base-content/30
                    "
                >
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon
                            name="lucide.link"
                            class="!size-3 stroke-[1.6]"
                        />
                        اتصال با SSH
                    </span>

                    <span aria-hidden="true" class="text-base-content/15">•</span>

                    <span class="inline-flex items-center gap-1.5">
                        <x-icon
                            name="lucide.cloud"
                            class="!size-3 stroke-[1.6]"
                        />
                        VPS ابری
                    </span>
                </div>
            </div>
        </section>
    @endif
</div>