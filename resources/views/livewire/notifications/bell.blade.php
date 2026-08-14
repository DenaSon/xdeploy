<div
    wire:poll.visible.60s="refreshNotifications"
>
    <x-dropdown>
        <x-slot:trigger>
            <div
                class="
                    tooltip tooltip-bottom

                    before:z-50
                    before:text-xs

                    after:z-50
                "
                data-tip="اعلان‌ها"
            >
                <button
                    type="button"
                    aria-label="{{ $unreadCount > 0
                        ? $unreadCount . ' اعلان خوانده‌نشده'
                        : 'اعلان‌ها'
                    }}"
                    class="
                        btn
                        btn-square
                        btn-ghost
                        btn-sm

                        relative

                        rounded-xl

                        text-base-content/55

                        hover:bg-base-200/70
                        hover:text-base-content
                    "
                >
                    <x-icon
                        name="lucide.bell"
                        class="!size-[18px] stroke-[1.7]"
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

                                text-[9px]
                                font-semibold
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
                w-[min(23rem,calc(100vw-2rem))]

                overflow-hidden
                rounded-2xl

                border border-base-300/80
                bg-base-100/95

                shadow-xl
                shadow-base-content/[0.07]

                backdrop-blur-xl
            "
        >
            {{-- Header --}}
            <header
                class="
                    flex
                    items-center justify-between
                    gap-3

                    border-b border-base-300/70

                    px-4 py-3.5
                "
            >
                <div>
                    <div
                        class="
                            flex
                            items-center gap-2
                        "
                    >
                        <h2
                            class="
                                text-sm
                                font-semibold
                                text-base-content
                            "
                        >
                            اعلان‌ها
                        </h2>

                        @if($unreadCount > 0)
                            <span
                                class="
                                    inline-flex
                                    min-w-5
                                    items-center justify-center

                                    rounded-full
                                    bg-primary/10

                                    px-1.5 py-0.5

                                    text-[9px]
                                    font-semibold
                                    text-primary
                                "
                            >
                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            </span>
                        @endif
                    </div>

                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-base-content/35
                        "
                    >
                        @if($unreadCount > 0)
                            اعلان‌های جدید نیازمند بررسی هستند
                        @else
                            اعلان جدیدی وجود ندارد
                        @endif
                    </p>
                </div>


                <a
                    href="{{ route('panel.notifications.index') }}"
                    wire:navigate
                    class="
                        inline-flex
                        items-center gap-1

                        rounded-lg

                        px-2 py-1.5

                        text-[11px]
                        font-medium
                        text-primary

                        transition-colors
                        duration-150

                        hover:bg-primary/[0.07]
                    "
                >
                    مشاهده همه

                    <x-icon
                        name="lucide.arrow-left"
                        class="!size-3 stroke-[1.8]"
                    />
                </a>
            </header>


            @if($notifications->isEmpty())

                {{-- Empty --}}
                <div
                    class="
                        px-5 py-10
                        text-center
                    "
                >
                    <span
                        class="
                            mx-auto

                            flex size-10
                            items-center justify-center

                            rounded-xl
                            bg-base-200/70
                            text-base-content/30
                        "
                    >
                        <x-icon
                            name="lucide.bell-off"
                            class="!size-4.5 stroke-[1.7]"
                        />
                    </span>

                    <div
                        class="
                            mt-3

                            text-xs
                            font-medium
                            text-base-content/55
                        "
                    >
                        اعلانی وجود ندارد
                    </div>

                    <p
                        class="
                            mt-1

                            text-[10px]
                            leading-5
                            text-base-content/35
                        "
                    >
                        رویدادهای مهم پس از ثبت در این بخش نمایش داده می‌شوند.
                    </p>
                </div>

            @else

                {{-- Recent notifications --}}
                <div
                    class="
                        divide-y
                        divide-base-300/60
                    "
                >
                    @foreach($notifications as $notification)
                        <x-notifications.item
                            :notification="$notification"
                            compact
                            wire:key="header-notification-{{ $notification->id }}"
                        />
                    @endforeach
                </div>


                {{-- Dropdown footer --}}
                <div
                    class="
                        border-t border-base-300/70
                        bg-base-200/20

                        px-3 py-2
                    "
                >
                    <a
                        href="{{ route('panel.notifications.index') }}"
                        wire:navigate
                        class="
                            flex
                            items-center justify-center
                            gap-1.5

                            rounded-lg

                            px-3 py-2

                            text-[11px]
                            font-medium
                            text-base-content/50

                            transition-colors
                            duration-150

                            hover:bg-base-200/70
                            hover:text-base-content
                        "
                    >
                        مدیریت همه اعلان‌ها

                        <x-icon
                            name="lucide.arrow-left"
                            class="!size-3.5 stroke-[1.7]"
                        />
                    </a>
                </div>
            @endif
        </div>
    </x-dropdown>
</div>
