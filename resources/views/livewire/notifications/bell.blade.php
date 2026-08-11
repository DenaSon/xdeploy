<div
    wire:poll.visible.60s="refreshNotifications"
>
    <x-dropdown>
        <x-slot:trigger>
            <div
                class="
                    tooltip tooltip-bottom
                    before:z-50 before:text-xs
                    after:z-50
                "
                data-tip="اعلان‌ها"
            >
                <button
                    type="button"
                    aria-label="اعلان‌ها"
                    class="
                        btn btn-square
                        btn-ghost btn-sm
                        relative
                    "
                >
                    <x-icon
                        name="lucide.bell"
                        class="!size-4.5"
                    />

                    @if($unreadCount > 0)
                        <span
                            class="
                                absolute
                                -end-0.5 -top-0.5
                                flex min-w-4
                                items-center justify-center
                                rounded-full
                                bg-primary
                                px-1
                                text-[9px] font-semibold
                                leading-4
                                text-primary-content
                                ring-2 ring-base-100
                            "
                        >
                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        </span>
                    @endif
                </button>
            </div>
        </x-slot:trigger>

        <div
            class="
                w-[min(22rem,calc(100vw-2rem))]
                overflow-hidden
                rounded-2xl
                border border-base-300
                bg-base-100
                shadow-lg
            "
        >
            <header
                class="
                    flex items-center
                    justify-between gap-3
                    border-b border-base-300
                    px-4 py-3
                "
            >
                <div>
                    <h2
                        class="
                            text-sm font-semibold
                            text-base-content
                        "
                    >
                        اعلان‌ها
                    </h2>

                    @if($unreadCount > 0)
                        <p
                            class="
                                mt-0.5
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            {{ $unreadCount }} اعلان خوانده‌نشده
                        </p>
                    @endif
                </div>

                <a
                    href="{{ route('panel.notifications.index') }}"
                    wire:navigate
                    class="
                        text-xs font-medium
                        text-primary
                        transition-opacity
                        hover:opacity-70
                    "
                >
                    مشاهده همه
                </a>
            </header>

            @if($notifications->isEmpty())
                <div
                    class="
                        px-5 py-9
                        text-center
                    "
                >
                    <div
                        class="
                            mx-auto
                            flex size-10
                            items-center justify-center
                            rounded-xl
                            bg-base-200
                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.bell-off"
                            class="!size-4.5"
                        />
                    </div>

                    <p
                        class="
                            mt-3
                            text-sm font-medium
                            text-base-content/60
                        "
                    >
                        اعلانی ندارید
                    </p>
                </div>
            @else
                <div class="divide-y divide-base-300">
                    @foreach($notifications as $notification)
                        <x-notifications.item
                            :notification="$notification"
                            compact
                            wire:key="header-notification-{{ $notification->id }}"
                        />
                    @endforeach
                </div>
            @endif
        </div>
    </x-dropdown>
</div>
