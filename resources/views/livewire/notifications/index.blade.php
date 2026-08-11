<div
    dir="rtl"
    class="space-y-5"
>
    <header
        class="
            flex flex-col gap-4
            sm:flex-row
            sm:items-end
            sm:justify-between
        "
    >
        <div class="min-w-0">
            <div
                class="
                    flex items-center gap-3
                "
            >
                <div
                    class="
                        flex size-10 shrink-0
                        items-center justify-center
                        rounded-xl
                        border border-primary/15
                        bg-primary/5
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.bell"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div>
                    <h1
                        class="
                            text-xl font-semibold
                            tracking-tight
                            text-base-content
                            sm:text-2xl
                        "
                    >
                        اعلان‌ها
                    </h1>

                    <p
                        class="
                            mt-1
                            text-xs leading-6
                            text-base-content/45
                            sm:text-sm
                        "
                    >
                        رویدادهای مهم سرویس‌ها و زیرساخت xDeploy را از اینجا دنبال کنید.
                    </p>
                </div>
            </div>
        </div>

        @if($unreadCount > 0)
            <x-button
                label="خواندن همه"
                icon="lucide.check-check"
                wire:click="markAllAsRead"
                spinner="markAllAsRead"
                class="
                    btn-ghost btn-sm
                    self-start rounded-xl
                    sm:self-auto
                "
            />
        @endif
    </header>

    <div
        class="
            flex items-center gap-1
            rounded-xl
            border border-base-300
            bg-base-100
            p-1
            sm:w-fit
        "
    >
        <button
            type="button"
            wire:click="setFilter('all')"
            @class([
                '
                    rounded-lg
                    px-3 py-1.5
                    text-xs font-medium
                    transition-colors
                ',
                'bg-base-200 text-base-content' =>
                    $filter === 'all',
                'text-base-content/45 hover:text-base-content' =>
                    $filter !== 'all',
            ])
        >
            همه
        </button>

        <button
            type="button"
            wire:click="setFilter('unread')"
            @class([
                '
                    rounded-lg
                    px-3 py-1.5
                    text-xs font-medium
                    transition-colors
                ',
                'bg-base-200 text-base-content' =>
                    $filter === 'unread',
                'text-base-content/45 hover:text-base-content' =>
                    $filter !== 'unread',
            ])
        >
            خوانده‌نشده

            @if($unreadCount > 0)
                <span
                    class="
                        ms-1
                        inline-flex min-w-4
                        items-center justify-center
                        rounded-full
                        bg-primary/10
                        px-1
                        text-[9px]
                        text-primary
                    "
                >
                    {{ $unreadCount }}
                </span>
            @endif
        </button>
    </div>

    @if($notifications->isEmpty())
        <section
            class="
                rounded-2xl
                border border-base-300
                bg-base-100
                px-5 py-14
                text-center
            "
        >
            <div
                class="
                    mx-auto
                    flex size-12
                    items-center justify-center
                    rounded-2xl
                    bg-base-200
                    text-base-content/35
                "
            >
                <x-icon
                    :name="$filter === 'unread'
                        ? 'lucide.check-check'
                        : 'lucide.bell-off'"
                    class="!size-5"
                />
            </div>

            <h2
                class="
                    mt-4
                    text-sm font-semibold
                    text-base-content
                "
            >
                {{ $filter === 'unread'
                    ? 'اعلان خوانده‌نشده‌ای ندارید'
                    : 'هنوز اعلانی ثبت نشده است' }}
            </h2>

            <p
                class="
                    mx-auto mt-1.5
                    max-w-md
                    text-xs leading-6
                    text-base-content/45
                "
            >
                اعلان‌های مرتبط با پایان سرویس و رویدادهای مهم زیرساخت در این صفحه نمایش داده می‌شوند.
            </p>
        </section>
    @else
        <section class="space-y-2.5">
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
