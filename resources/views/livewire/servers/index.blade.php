<div
    dir="rtl"
    class="space-y-7"
>
    {{-- Page header --}}
    <header
        class="
            flex flex-col gap-5

            sm:flex-row
            sm:items-end
            sm:justify-between
        "
    >
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <span
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
                </span>

                <div class="min-w-0">
                    <div
                        class="
                            flex flex-wrap
                            items-center gap-2
                        "
                    >
                        <h1
                            class="
                                text-2xl
                                font-semibold
                                tracking-tight
                                text-base-content
                            "
                        >
                            سرورها
                        </h1>

                        @if($servers->isNotEmpty())
                            <span
                                class="
                                    inline-flex
                                    min-w-6
                                    items-center justify-center

                                    rounded-full
                                    bg-base-200

                                    px-2 py-0.5

                                    text-[11px]
                                    font-medium
                                    text-base-content/45
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
                        سرورهای متصل و VPSهای تهیه‌شده را از این بخش مدیریت کنید.
                    </p>
                </div>
            </div>
        </div>


        {{-- Primary page actions --}}
        <div
            class="
                flex
                flex-wrap
                items-center gap-2
            "
        >
            <x-button
                label="اتصال VPS موجود"
                icon="lucide.link-2"
                :link="route('panel.servers.create')"
                wire:navigate
                class="
                    btn-ghost

                    rounded-xl
                    border border-base-300

                    px-4

                    font-medium

                    hover:border-primary/20
                    hover:bg-base-200/60
                "
            />

            <x-button
                label="تهیه VPS جدید"
                icon="lucide.cloud"
                :link="route('panel.servers.buy')"
                wire:navigate
                class="
                    btn-primary
                    rounded-xl

                    px-4

                    font-medium

                    shadow-sm
                    shadow-primary/10
                "
            />
        </div>
    </header>


    @if($servers->isNotEmpty())
        {{-- Server list --}}
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
                    x-data="{ actionsOpen: false }"
                    @keydown.escape.window="actionsOpen = false"
                    @class([
                        '
                            group
                            relative

                            rounded-2xl

                            border

                            p-4

                            transition-all
                            duration-200

                            hover:-translate-y-px
                            hover:shadow-lg
                            hover:shadow-base-content/[0.025]

                            sm:p-5
                        ',
                        '
                            border-primary/15
                            bg-primary/[0.025]
                            hover:border-primary/25
                        ' => $server->isActive(),
                        '
                            border-base-300/80
                            bg-base-100
                            hover:border-primary/20
                        ' => ! $server->isActive(),
                    ])
                >
                    <div
                        class="
                            flex
                            flex-col gap-4

                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                        "
                    >
                        {{-- Identity --}}
                        <div
                            class="
                                flex min-w-0
                                items-center gap-3.5
                            "
                        >
                            {{-- Server icon --}}
                            <div
                                @class([
                                    '
                                        flex size-12 shrink-0
                                        items-center justify-center

                                        rounded-2xl

                                        ring-1
                                    ',
                                    '
                                        bg-base-200/70
                                        text-base-content/55
                                        ring-base-300/60
                                    ' => $server->isUserProvided(),
                                    '
                                        bg-primary/10
                                        text-primary
                                        ring-primary/10
                                    ' => ! $server->isUserProvided(),
                                ])
                            >
                                <x-icon
                                    :name="$server->isUserProvided()
                                        ? 'lucide.server'
                                        : 'lucide.cloud'"
                                    class="!size-5 stroke-[1.7]"
                                />
                            </div>


                            <div class="min-w-0">
                                {{-- Name + status --}}
                                <div
                                    class="
                                        flex min-w-0
                                        flex-wrap
                                        items-center gap-2
                                    "
                                >
                                    <h3
                                        class="
                                            max-w-56
                                            truncate

                                            text-sm
                                            font-semibold
                                            tracking-tight
                                            text-base-content

                                            sm:max-w-72
                                            sm:text-base
                                        "
                                    >
                                        {{ $server->name }}
                                    </h3>


                                    <span
                                        @class([
                                            '
                                                inline-flex
                                                shrink-0
                                                items-center gap-1.5

                                                rounded-full

                                                px-2.5 py-1

                                                text-[10px]
                                                font-medium
                                            ',
                                            '
                                                bg-success/10
                                                text-success
                                            ' => $server->isActive(),
                                            '
                                                bg-base-200
                                                text-base-content/40
                                            ' => ! $server->isActive(),
                                        ])
                                    >
                                        <span
                                            @class([
                                                '
                                                    size-1.5
                                                    rounded-full
                                                ',
                                                'bg-success' =>
                                                    $server->isActive(),
                                                'bg-base-content/25' =>
                                                    ! $server->isActive(),
                                            ])
                                        ></span>

                                        {{ $server->isActive()
                                            ? 'آماده مدیریت'
                                            : 'غیرفعال'
                                        }}
                                    </span>
                                </div>


                                {{-- Metadata --}}
                                <div
                                    class="
                                        mt-2

                                        flex min-w-0
                                        flex-wrap
                                        items-center

                                        gap-x-3 gap-y-1.5

                                        text-xs
                                        text-base-content/40
                                    "
                                >
                                    {{-- Source --}}
                                    <span
                                        class="
                                            inline-flex
                                            items-center gap-1.5
                                        "
                                    >
                                        <x-icon
                                            :name="$server->isUserProvided()
                                                ? 'lucide.link-2'
                                                : 'lucide.cloud'"
                                            class="
                                                !size-3.5
                                                stroke-[1.6]
                                            "
                                        />

                                        {{ $server->isUserProvided()
                                            ? 'VPS متصل'
                                            : 'VPS ابری'
                                        }}
                                    </span>


                                    {{-- Separator --}}
                                    <span
                                        aria-hidden="true"
                                        class="text-base-content/15"
                                    >
                                        |
                                    </span>


                                    {{-- Host --}}
                                    <span
                                        class="
                                            inline-flex
                                            min-w-0
                                            items-center gap-1.5
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
                                    </span>
                                </div>
                            </div>
                        </div>


                        {{-- Desktop actions --}}
                        <div
                            class="
                                hidden shrink-0
                                items-center gap-1.5

                                sm:flex
                            "
                        >
                            <x-button
                                label="مدیریت"
                                icon="lucide.arrow-left"
                                :link="route('panel.servers.dashboard', $server)"
                                wire:navigate
                                :aria-label="'مدیریت سرور ' . $server->name"
                                class="
                                    btn-primary
                                    btn-sm
                                    rounded-xl

                                    px-4

                                    font-medium

                                    shadow-sm
                                    shadow-primary/10
                                "
                            />


                            <div
                                class="tooltip tooltip-top"
                                data-tip="ویرایش اتصال"
                            >
                                <x-button
                                    icon="lucide.pencil"
                                    :link="route('panel.servers.edit', $server)"
                                    wire:navigate
                                    :aria-label="'ویرایش اتصال سرور ' . $server->name"
                                    class="
                                        btn-ghost
                                        btn-square
                                        btn-sm

                                        rounded-xl

                                        text-base-content/45

                                        hover:bg-base-200
                                        hover:text-base-content
                                    "
                                />
                            </div>


                            @if($server->isUserProvided())
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

                                            text-error/55

                                            hover:bg-error/10
                                            hover:text-error
                                        "
                                    />
                                </div>
                            @endif
                        </div>


                        {{-- Mobile actions --}}
                        <div
                            class="
                                relative

                                flex
                                items-center gap-2

                                border-t border-base-300/60

                                pt-3

                                sm:hidden
                            "
                        >
                            <x-button
                                label="مدیریت سرور"
                                icon="lucide.arrow-left"
                                :link="route('panel.servers.dashboard', $server)"
                                wire:navigate
                                class="
                                    btn-primary
                                    btn-sm

                                    flex-1
                                    rounded-xl

                                    font-medium
                                "
                            />


                            <button
                                type="button"
                                @click="actionsOpen = ! actionsOpen"
                                :aria-expanded="actionsOpen"
                                aria-label="گزینه‌های بیشتر"
                                class="
                                    btn
                                    btn-square
                                    btn-ghost
                                    btn-sm

                                    rounded-xl

                                    border border-base-300

                                    text-base-content/50

                                    hover:bg-base-200
                                    hover:text-base-content
                                "
                            >
                                <x-icon
                                    name="lucide.ellipsis"
                                    class="!size-4 stroke-[1.8]"
                                />
                            </button>


                            {{-- Mobile overflow menu --}}
                            <div
                                x-cloak
                                x-show="actionsOpen"
                                x-transition:enter="
                                    transition
                                    ease-out
                                    duration-150
                                "
                                x-transition:enter-start="
                                    opacity-0
                                    scale-95
                                    translate-y-1
                                "
                                x-transition:enter-end="
                                    opacity-100
                                    scale-100
                                    translate-y-0
                                "
                                x-transition:leave="
                                    transition
                                    ease-in
                                    duration-100
                                "
                                x-transition:leave-start="
                                    opacity-100
                                    scale-100
                                "
                                x-transition:leave-end="
                                    opacity-0
                                    scale-95
                                "
                                @click.outside="actionsOpen = false"
                                class="
                                    absolute
                                    bottom-11
                                    end-0
                                    z-20

                                    w-48

                                    origin-bottom-left

                                    overflow-hidden
                                    rounded-xl

                                    border border-base-300/80
                                    bg-base-100/95

                                    p-1.5

                                    shadow-xl
                                    shadow-base-content/[0.07]

                                    backdrop-blur-xl
                                "
                            >
                                <a
                                    href="{{ route('panel.servers.edit', $server) }}"
                                    wire:navigate
                                    @click="actionsOpen = false"
                                    class="
                                        flex
                                        w-full
                                        items-center gap-2.5

                                        rounded-lg

                                        px-3 py-2.5

                                        text-xs
                                        font-medium
                                        text-base-content/65

                                        transition-colors
                                        duration-150

                                        hover:bg-base-200
                                        hover:text-base-content
                                    "
                                >
                                    <x-icon
                                        name="lucide.pencil"
                                        class="!size-4 stroke-[1.7]"
                                    />

                                    ویرایش اتصال
                                </a>


                                @if($server->isUserProvided())
                                    <div
                                        class="
                                            my-1
                                            h-px
                                            bg-base-300/70
                                        "
                                    ></div>

                                    <button
                                        type="button"
                                        wire:click="delete({{ $server->id }})"
                                        wire:confirm="آیا از حذف سرور «{{ $server->name }}» مطمئن هستید؟"
                                        wire:target="delete({{ $server->id }})"
                                        @click="actionsOpen = false"
                                        class="
                                            flex
                                            w-full
                                            items-center gap-2.5

                                            rounded-lg

                                            px-3 py-2.5

                                            text-xs
                                            font-medium
                                            text-error/70

                                            transition-colors
                                            duration-150

                                            hover:bg-error/10
                                            hover:text-error
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
                relative
                overflow-hidden

                rounded-2xl

                border border-base-300/80
                bg-base-100

                px-5 py-14

                text-center

                sm:px-8
                sm:py-16
            "
        >
            {{-- Soft atmosphere --}}
            <div
                aria-hidden="true"
                class="
                    pointer-events-none

                    absolute
                    start-1/2 top-10

                    size-72
                    -translate-x-1/2

                    rounded-full
                    bg-primary/[0.055]
                    blur-3xl
                "
            ></div>


            <div
                class="
                    relative
                    mx-auto
                    max-w-lg
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

                        ring-1 ring-primary/10
                    "
                >
                    <x-icon
                        name="lucide.server-plus"
                        class="!size-6 stroke-[1.7]"
                    />
                </div>


                <h2
                    class="
                        mt-5

                        text-lg
                        font-semibold
                        tracking-tight
                        text-base-content
                    "
                >
                    هنوز سروری اضافه نشده است
                </h2>


                <p
                    class="
                        mx-auto mt-2
                        max-w-md

                        text-sm
                        leading-7
                        text-base-content/50
                    "
                >
                    VPS موجود خود را متصل کنید یا یک سرور جدید تهیه کنید
                    تا مدیریت سرور و برنامه‌های موردنیاز را آغاز کنید.
                </p>


                <div
                    class="
                        mt-7

                        flex flex-col
                        items-stretch
                        justify-center
                        gap-2

                        sm:flex-row
                        sm:items-center
                    "
                >
                    <x-button
                        label="اتصال VPS موجود"
                        icon="lucide.link-2"
                        :link="route('panel.servers.create')"
                        wire:navigate
                        class="
                            btn-ghost

                            rounded-xl
                            border border-base-300

                            px-5

                            font-medium
                        "
                    />

                    <x-button
                        label="تهیه VPS جدید"
                        icon="lucide.cloud"
                        :link="route('panel.servers.buy')"
                        wire:navigate
                        class="
                            btn-primary
                            rounded-xl

                            px-5

                            font-medium

                            shadow-sm
                            shadow-primary/10
                        "
                    />
                </div>


                {{-- Supporting options --}}
                <div
                    class="
                        mt-7

                        flex flex-wrap
                        items-center justify-center

                        gap-x-4 gap-y-2

                        text-[11px]
                        text-base-content/35
                    "
                >
                    <span
                        class="
                            inline-flex
                            items-center gap-1.5
                        "
                    >
                        <x-icon
                            name="lucide.link"
                            class="!size-3.5 stroke-[1.6]"
                        />

                        اتصال با SSH
                    </span>

                    <span
                        aria-hidden="true"
                        class="text-base-content/15"
                    >
                        |
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

                        تهیه VPS ابری
                    </span>
                </div>
            </div>
        </section>
    @endif
</div>
