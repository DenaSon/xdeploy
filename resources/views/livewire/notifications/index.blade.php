<div
    dir="rtl"
    class="mx-auto w-full max-w-6xl space-y-6"
>
    {{-- Page header --}}
    <header
        class="
            flex flex-col gap-4

            sm:flex-row
            sm:items-end
            sm:justify-between
        "
    >
        <div
            class="
                flex min-w-0
                items-start gap-3.5
            "
        >
            <span
                class="
                    flex size-10 shrink-0
                    items-center justify-center

                    rounded-2xl
                    bg-primary/10
                    text-primary

                    ring-1 ring-primary/10
                "
            >
                <x-icon
                    name="lucide.bell"
                    class="!size-[18px] stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">
                <div
                    class="
                        flex flex-wrap
                        items-center gap-2.5
                    "
                >
                    <h1
                        class="
                            text-xl
                            font-semibold
                            tracking-tight
                            text-base-content

                            sm:text-2xl
                        "
                    >
                        اعلان‌ها
                    </h1>

                    @if($unreadCount > 0)
                        <span
                            class="
                                inline-flex
                                items-center gap-1.5

                                rounded-full
                                bg-primary/[0.08]

                                px-2.5 py-1

                                text-[10px]
                                font-medium
                                text-primary
                            "
                        >
                            <span
                                class="
                                    size-1.5
                                    rounded-full
                                    bg-primary
                                "
                            ></span>

                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            جدید
                        </span>
                    @endif
                </div>

                <p
                    class="
                        mt-1
                        max-w-xl

                        text-xs
                        leading-6
                        text-base-content/45

                        sm:text-sm
                    "
                >
                    رویدادهای مهم سرورها، سرویس‌ها و زیرساخت را از اینجا دنبال کنید.
                </p>
            </div>
        </div>
    </header>


    {{-- Toolbar --}}
    <section
        aria-label="فیلتر اعلان‌ها"
        class="
            flex flex-col gap-3

            rounded-2xl
            border border-base-300/65
            bg-base-100/70

            p-2.5

            sm:flex-row
            sm:items-center
            sm:justify-between
        "
    >
        {{-- Filters --}}
        <div
            class="
                inline-flex self-start
                items-center gap-1

                rounded-xl
                bg-base-200/55

                p-1
            "
        >
            <button
                type="button"
                wire:click="setFilter('all')"
                wire:loading.attr="disabled"
                wire:target="setFilter"
                @class([
                    '
                        rounded-lg
                        px-3.5 py-1.5
                        text-xs font-medium
                        transition-all duration-150
                    ',
                    '
                        bg-base-100
                        text-base-content
                        shadow-sm shadow-base-content/[0.025]
                    ' => $filter === 'all',
                    '
                        text-base-content/45
                        hover:text-base-content/70
                    ' => $filter !== 'all',
                ])
            >
                همه
            </button>

            <button
                type="button"
                wire:click="setFilter('unread')"
                wire:loading.attr="disabled"
                wire:target="setFilter"
                @class([
                    '
                        inline-flex
                        items-center gap-1.5

                        rounded-lg
                        px-3.5 py-1.5

                        text-xs font-medium
                        transition-all duration-150
                    ',
                    '
                        bg-base-100
                        text-base-content
                        shadow-sm shadow-base-content/[0.025]
                    ' => $filter === 'unread',
                    '
                        text-base-content/45
                        hover:text-base-content/70
                    ' => $filter !== 'unread',
                ])
            >
                خوانده‌نشده

                @if($unreadCount > 0)
                    <span
                        class="
                            inline-flex min-w-4
                            items-center justify-center

                            rounded-full
                            bg-primary/10

                            px-1

                            text-[9px]
                            font-semibold
                            text-primary
                        "
                    >
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </span>
                @endif
            </button>
        </div>


        <div
            class="
                flex min-h-8
                items-center gap-2

                sm:justify-end
            "
        >
            {{-- Loading --}}
            <div
                wire:loading
                wire:target="setFilter,markAllAsRead"
                class="
                    items-center gap-2
                    text-[10px]
                    text-base-content/35
                "
            >
                <span
                    class="
                        loading
                        loading-spinner
                        loading-xs
                        text-primary
                    "
                ></span>

                در حال به‌روزرسانی
            </div>

            @if($unreadCount > 0)
                <x-button
                    label="همه را خواندم"
                    icon="lucide.check-check"
                    wire:click="markAllAsRead"
                    wire:target="markAllAsRead"
                    spinner
                    aria-label="علامت‌گذاری همه اعلان‌ها به‌عنوان خوانده‌شده"
                    class="
                        btn-ghost
                        btn-sm

                        rounded-xl
                        px-3.5

                        font-medium
                        text-base-content/55

                        hover:bg-base-200/70
                        hover:text-base-content
                    "
                />
            @endif
        </div>
    </section>


    {{-- Content --}}
    @if($notifications->isEmpty())
        <section
            class="
                relative
                overflow-hidden

                rounded-2xl
                border border-base-300/70
                bg-base-100

                px-5 py-14
                text-center

                sm:px-8
                sm:py-16
            "
        >
            <div
                aria-hidden="true"
                class="
                    pointer-events-none
                    absolute start-1/2 top-8

                    size-56
                    -translate-x-1/2

                    rounded-full
                    bg-primary/[0.04]
                    blur-3xl
                "
            ></div>

            <div
                class="
                    relative
                    mx-auto max-w-md
                "
            >
                <span
                    @class([
                        '
                            mx-auto
                            flex size-12
                            items-center justify-center
                            rounded-2xl
                        ',
                        '
                            bg-success/[0.08]
                            text-success
                        ' => $filter === 'unread',
                        '
                            bg-base-200/70
                            text-base-content/35
                        ' => $filter !== 'unread',
                    ])
                >
                    <x-icon
                        :name="$filter === 'unread'
                            ? 'lucide.check-check'
                            : 'lucide.bell-off'"
                        class="!size-5 stroke-[1.7]"
                    />
                </span>

                <h2
                    class="
                        mt-4
                        text-sm font-semibold
                        text-base-content
                    "
                >
                    {{ $filter === 'unread'
                        ? 'همه اعلان‌ها خوانده شده‌اند'
                        : 'هنوز اعلانی ثبت نشده است'
                    }}
                </h2>

                <p
                    class="
                        mx-auto mt-1.5
                        max-w-sm

                        text-xs leading-6
                        text-base-content/45
                    "
                >
                    @if($filter === 'unread')
                        در حال حاضر اعلان خوانده‌نشده‌ای وجود ندارد.
                    @else
                        رویدادهای مهم سرورها و سرویس‌ها پس از ثبت در این بخش نمایش داده می‌شوند.
                    @endif
                </p>
            </div>
        </section>
    @else
        <section
            aria-label="فهرست اعلان‌ها"
            class="space-y-2.5"
        >
            @foreach($notifications as $notification)
                <x-notifications.item
                    :notification="$notification"
                    wire:key="notification-{{ $notification->id }}"
                />
            @endforeach
        </section>

        @if($notifications->hasPages())
            <div class="pt-2">
                {{ $notifications->links() }}
            </div>
        @endif
    @endif
</div>
