<section
    x-data="{ addDeviceOpen: false }"
    class="
        overflow-hidden
        rounded-2xl

        border border-base-300/80
        bg-base-100

        shadow-sm
        shadow-base-content/[0.015]
    "
>
    {{-- Header --}}
    <header
        class="
            flex flex-col gap-4

            border-b border-base-300/70

            px-4 py-4

            sm:flex-row
            sm:items-center
            sm:justify-between
            sm:px-5
        "
    >
        <div
            class="
                flex min-w-0
                items-start gap-3
            "
        >
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
                    name="lucide.shield"
                    class="!size-[18px] stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">
                <div
                    class="
                        flex flex-wrap
                        items-center gap-2
                    "
                >
                    <h2
                        class="
                            text-base
                            font-semibold
                            text-base-content
                        "
                    >
                        دستگاه‌ها
                    </h2>

                    @if($peers !== [])
                        <span
                            class="
                                inline-flex
                                min-w-5
                                items-center justify-center

                                rounded-full
                                bg-base-200

                                px-1.5 py-0.5

                                text-[10px]
                                font-medium
                                text-base-content/45
                            "
                        >
                            {{ count($peers) }}
                        </span>
                    @endif
                </div>

                <p
                    class="
                        mt-1
                        max-w-2xl

                        text-xs
                        leading-6
                        text-base-content/45

                        sm:text-sm
                    "
                >
                    برای هر دستگاه یک دسترسی مستقل ایجاد کنید،
                    فایل پیکربندی را دریافت کنید و وضعیت دسترسی
                    هر دستگاه را جداگانه مدیریت کنید.
                </p>
            </div>
        </div>


        {{-- Header actions --}}
        <div
            class="
                flex shrink-0
                items-center gap-2
            "
        >
            <div
                class="
                    tooltip tooltip-bottom

                    before:z-50
                    before:whitespace-nowrap
                    before:text-xs

                    after:z-50
                "
                data-tip="به‌روزرسانی وضعیت"
            >
                <button
                    type="button"
                    wire:click="refreshPeers"
                    wire:loading.attr="disabled"
                    wire:target="refreshPeers"
                    aria-label="به‌روزرسانی وضعیت دستگاه‌ها"
                    class="
                        btn
                        btn-square
                        btn-ghost
                        btn-sm

                        rounded-xl

                        text-base-content/45

                        hover:bg-base-200
                        hover:text-primary
                    "
                >
                    <span
                        wire:loading.remove
                        wire:target="refreshPeers"
                    >
                        <x-icon
                            name="lucide.refresh-cw"
                            class="!size-4 stroke-[1.7]"
                        />
                    </span>

                    <span
                        wire:loading
                        wire:target="refreshPeers"
                        class="
                            loading
                            loading-spinner
                            loading-xs
                        "
                    ></span>
                </button>
            </div>


            <button
                type="button"
                @click="addDeviceOpen = ! addDeviceOpen"
                :aria-expanded="addDeviceOpen.toString()"
                class="
                    btn
                    btn-primary
                    btn-sm

                    gap-1.5
                    rounded-xl

                    px-3.5

                    font-medium
                "
            >
                <x-icon
                    name="lucide.plus"
                    class="
                        !size-4
                        stroke-[1.8]

                        transition-transform
                        duration-200
                    "
                    x-bind:class="addDeviceOpen
                        ? 'rotate-45'
                        : ''"
                />

                افزودن دستگاه
            </button>
        </div>
    </header>


    {{-- Success feedback --}}
    @if(session('status'))
        <div
            role="status"
            class="
                flex items-start gap-2.5

                border-b border-success/15
                bg-success/[0.04]

                px-4 py-3

                sm:px-5
            "
        >
            <x-icon
                name="lucide.circle-check"
                class="
                    mt-0.5
                    !size-4
                    shrink-0
                    text-success
                    stroke-[1.8]
                "
            />

            <p
                class="
                    text-xs
                    leading-6
                    text-base-content/55

                    sm:text-sm
                "
            >
                {{ session('status') }}
            </p>
        </div>
    @endif


    {{-- Create device --}}
    <div
        x-cloak
        x-show="addDeviceOpen"
        x-collapse
        class="
            border-b border-base-300/70
            bg-base-200/20
        "
    >
        <div
            class="
                px-4 py-4

                sm:px-5
            "
        >
            <div class="mb-3">
                <div
                    class="
                        text-sm
                        font-medium
                        text-base-content
                    "
                >
                    دستگاه جدید
                </div>

                <p
                    class="
                        mt-0.5
                        text-[11px]
                        leading-5
                        text-base-content/40
                    "
                >
                    برای دستگاه جدید یک دسترسی و فایل پیکربندی مستقل ایجاد می‌شود.
                </p>
            </div>


            <x-form
                wire:submit="createPeer"
                no-separator
            >
                <div
                    class="
            grid grid-cols-1
            gap-3

            md:grid-cols-[minmax(0,1fr)_auto]
            md:items-end
        "
                >
                    <x-input
                        label="نام دستگاه"
                        wire:model.blur="peerName"
                        icon="lucide.smartphone"
                        placeholder="برای مثال: iPhone"
                        maxlength="60"
                        wire:loading.attr="disabled"
                        wire:target="createPeer"
                    />

                    <x-button
                        label="ایجاد دسترسی"
                        icon="lucide.plus"
                        type="submit"
                        spinner="createPeer"
                        wire:loading.attr="disabled"
                        wire:target="createPeer"
                        class="
                btn-primary

                rounded-xl

                px-5

                font-medium

                md:h-12
            "
                    />
                </div>

                <p
                    class="
            mt-1.5

            text-[11px]
            leading-5
            text-base-content/40
        "
                >
                    این نام فقط برای شناسایی دستگاه در پنل استفاده می‌شود.
                </p>
            </x-form>


        </div>
    </div>


    {{-- Runtime warning --}}
    @if(! $runtimeAvailable)
        <div
            class="
                flex items-start gap-2.5

                border-b border-warning/15
                bg-warning/[0.035]

                px-4 py-3.5

                sm:px-5
            "
        >
            <x-icon
                name="lucide.triangle-alert"
                class="
                    mt-0.5
                    !size-4
                    shrink-0
                    text-warning
                    stroke-[1.8]
                "
            />

            <div>
                <div
                    class="
                        text-xs
                        font-medium
                        text-base-content/70

                        sm:text-sm
                    "
                >
                    وضعیت اجرایی دستگاه‌ها در دسترس نیست
                </div>

                <p
                    class="
                        mt-0.5

                        text-xs
                        leading-6
                        text-base-content/45
                    "
                >
                    اتصال سرور و وضعیت اجرای AmneziaWG را بررسی کنید
                    و سپس وضعیت دستگاه‌ها را دوباره به‌روزرسانی کنید.
                </p>
            </div>
        </div>
    @endif


    {{-- Devices --}}
    <div>
        @forelse($peers as $peer)
            @php
                $handshakeAt = $peer['latest_handshake_at'] ?? null;

                $handshakeLabel = is_int($handshakeAt) && $handshakeAt > 0
                    ? \Carbon\CarbonImmutable::createFromTimestamp($handshakeAt)
                        ->setTimezone(config('app.timezone'))
                        ->diffForHumans()
                    : 'هنوز متصل نشده';
            @endphp


            <article
                x-data="{ revokeOpen: false }"
                class="
                    border-b border-base-300/60
                    last:border-b-0
                "
            >
                <div
                    class="
                        flex flex-col gap-4

                        px-4 py-4

                        lg:flex-row
                        lg:items-center
                        lg:justify-between

                        sm:px-5
                    "
                >
                    {{-- Device identity --}}
                    <div
                        class="
                            flex min-w-0
                            items-start gap-3
                        "
                    >
                        <span
                            class="
                                flex size-10 shrink-0
                                items-center justify-center

                                rounded-xl
                                bg-base-200/70

                                text-base-content/45
                            "
                        >
                            <x-icon
                                name="lucide.smartphone"
                                class="!size-4.5 stroke-[1.7]"
                            />
                        </span>


                        <div class="min-w-0">
                            {{-- Name --}}
                            <div
                                class="
                                    flex flex-wrap
                                    items-center gap-2
                                "
                            >
                                <h3
                                    class="
                                        truncate

                                        text-sm
                                        font-semibold
                                        text-base-content
                                    "
                                >
                                    {{ $peer['name'] }}
                                </h3>


                                @if($peer['runtime_configured'])
                                    <span
                                        class="
                                            inline-flex
                                            items-center gap-1.5

                                            rounded-full
                                            bg-success/10

                                            px-2 py-0.5

                                            text-[10px]
                                            font-medium
                                            text-success
                                        "
                                    >
                                        <span
                                            class="
                                                size-1.5
                                                rounded-full
                                                bg-success
                                            "
                                        ></span>

                                        فعال
                                    </span>
                                @else
                                    <span
                                        class="
                                            inline-flex
                                            items-center gap-1.5

                                            rounded-full
                                            bg-warning/10

                                            px-2 py-0.5

                                            text-[10px]
                                            font-medium
                                            text-warning
                                        "
                                    >
                                        <span
                                            class="
                                                size-1.5
                                                rounded-full
                                                bg-warning
                                            "
                                        ></span>

                                        همگام‌نشده
                                    </span>
                                @endif
                            </div>


                            {{-- Metadata --}}
                            <div
                                class="
                                    mt-2

                                    flex flex-wrap
                                    items-center

                                    gap-x-3 gap-y-1.5

                                    text-[11px]
                                    text-base-content/40
                                "
                            >
                                {{-- IP --}}
                                <span
                                    class="
                                        inline-flex
                                        items-center gap-1.5
                                    "
                                >
                                    <x-icon
                                        name="lucide.network"
                                        class="!size-3 stroke-[1.6]"
                                    />

                                    <span
                                        dir="ltr"
                                        class="
                                            technical-value
                                            text-base-content/55
                                        "
                                    >
                                        {{ $peer['ip_address'] }}
                                    </span>
                                </span>


                                <span
                                    aria-hidden="true"
                                    class="text-base-content/15"
                                >
                                    |
                                </span>


                                {{-- Handshake --}}
                                <span
                                    class="
                                        inline-flex
                                        items-center gap-1.5
                                    "
                                >
                                    <x-icon
                                        name="lucide.radio"
                                        class="!size-3 stroke-[1.6]"
                                    />

                                    آخرین ارتباط:
                                    <span class="text-base-content/55">
                                        {{ $handshakeLabel }}
                                    </span>
                                </span>


                                <span
                                    aria-hidden="true"
                                    class="
                                        hidden
                                        text-base-content/15

                                        sm:inline
                                    "
                                >
                                    |
                                </span>


                                {{-- Traffic --}}
                                <span
                                    dir="ltr"
                                    class="
                                        inline-flex
                                        items-center gap-2

                                        technical-value
                                        text-base-content/50
                                    "
                                >
                                    <span>
                                        ↓
                                        {{ number_format(
                                            (int) $peer['received_bytes']
                                        ) }}
                                        B
                                    </span>

                                    <span>
                                        ↑
                                        {{ number_format(
                                            (int) $peer['sent_bytes']
                                        ) }}
                                        B
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>


                    {{-- Actions --}}
                    <div
                        class="
                            flex shrink-0
                            flex-wrap
                            items-center gap-1.5

                            lg:justify-end
                        "
                    >
                        <a
                            href="{{ route(
        'panel.servers.applications.amneziawg.peers.config',
        [
            $serverId,
            $peer['id'],
        ],
    ) }}"
                            download
                            class="
        btn
        btn-ghost
        btn-sm

        gap-1.5
        rounded-xl

        text-base-content/60

        hover:bg-base-200
        hover:text-base-content
    "
                        >
                            <x-icon
                                name="lucide.download"
                                class="!size-4 stroke-[1.7]"
                            />

                            دریافت کانفیگ
                        </a>


                        <button
                            type="button"
                            @click="revokeOpen = ! revokeOpen"
                            :aria-expanded="revokeOpen.toString()"
                            class="
                                btn
                                btn-ghost
                                btn-sm

                                rounded-xl

                                text-error/65

                                hover:bg-error/10
                                hover:text-error
                            "
                        >
                            <x-icon
                                name="lucide.user-x"
                                class="!size-4 stroke-[1.7]"
                            />

                            لغو دسترسی
                        </button>
                    </div>
                </div>


                {{-- Revoke confirmation --}}
                <div
                    x-cloak
                    x-show="revokeOpen"
                    x-collapse
                    class="
                        border-t border-error/10
                        bg-error/[0.025]
                    "
                >
                    <div
                        class="
                            flex flex-col gap-3

                            px-4 py-3.5

                            sm:flex-row
                            sm:items-center
                            sm:justify-between

                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-start gap-2.5
                            "
                        >
                            <x-icon
                                name="lucide.triangle-alert"
                                class="
                                    mt-0.5
                                    !size-4
                                    shrink-0
                                    text-error
                                    stroke-[1.8]
                                "
                            />

                            <div>
                                <div
                                    class="
                                        text-xs
                                        font-medium
                                        text-base-content/70
                                    "
                                >
                                    دسترسی این دستگاه لغو شود؟
                                </div>

                                <p
                                    class="
                                        mt-0.5

                                        text-[11px]
                                        leading-5
                                        text-base-content/45
                                    "
                                >
                                    پس از لغو دسترسی، فایل پیکربندی فعلی
                                    دیگر قابل استفاده نخواهد بود.
                                </p>
                            </div>
                        </div>


                        <div
                            class="
                                flex shrink-0
                                items-center gap-2
                            "
                        >
                            <button
                                type="button"
                                @click="revokeOpen = false"
                                class="
                                    btn
                                    btn-ghost
                                    btn-sm

                                    rounded-xl

                                    text-base-content/50
                                "
                            >
                                انصراف
                            </button>


                            <form
                                method="POST"
                                action="{{ route(
                                    'panel.servers.applications.amneziawg.peers.deactivate',
                                    [
                                        $serverId,
                                        $peer['id'],
                                    ],
                                ) }}"
                            >
                                @csrf

                                <x-button
                                    label="لغو دسترسی"
                                    icon="lucide.user-x"
                                    type="submit"
                                    class="
                                        btn-error
                                        btn-sm
                                        rounded-xl

                                        font-medium
                                    "
                                />
                            </form>
                        </div>
                    </div>
                </div>
            </article>


        @empty
            {{-- Empty state --}}
            <div
                class="
                    px-5 py-12

                    text-center

                    sm:px-6
                "
            >
                <span
                    class="
                        mx-auto

                        flex size-11
                        items-center justify-center

                        rounded-xl
                        bg-base-200/70

                        text-base-content/35
                    "
                >
                    <x-icon
                        name="lucide.smartphone"
                        class="!size-4.5 stroke-[1.7]"
                    />
                </span>


                <h3
                    class="
                        mt-3

                        text-sm
                        font-semibold
                        text-base-content
                    "
                >
                    هنوز دستگاهی اضافه نشده است
                </h3>


                <p
                    class="
                        mx-auto mt-1
                        max-w-sm

                        text-xs
                        leading-6
                        text-base-content/45
                    "
                >
                    برای ایجاد اولین دسترسی، یک دستگاه جدید اضافه کنید.
                </p>


                <button
                    type="button"
                    @click="addDeviceOpen = true; $nextTick(() => $el.closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    class="
                        btn
                        btn-primary
                        btn-sm

                        mt-4
                        rounded-xl

                        px-4

                        font-medium
                    "
                >
                    <x-icon
                        name="lucide.plus"
                        class="!size-4 stroke-[1.8]"
                    />

                    افزودن اولین دستگاه
                </button>
            </div>
        @endforelse
    </div>
</section>
