@props([
    'docker' => [],
])

@php
    $docker = is_array($docker)
        ? $docker
        : [];

    $installed = (bool) (
        $docker['installed']
        ?? false
    );

    $accessible = (bool) (
        $docker['accessible']
        ?? false
    );

    $containers = is_array(
        $docker['containers']
        ?? null
    )
        ? array_values(
            array_filter(
                $docker['containers'],
                static fn (mixed $container): bool =>
                    is_array($container),
            ),
        )
        : [];

    $containerCount = count(
        $containers,
    );

    $runningCount = isset(
        $docker['running_count'],
    )
        && is_numeric(
            $docker['running_count'],
        )
            ? (int) $docker['running_count']
            : count(
                array_filter(
                    $containers,
                    static fn (array $container): bool =>
                        strtolower(
                            (string) (
                                $container['state']
                                ?? ''
                            ),
                        ) === 'running',
                ),
            );

    $statePresentation = static function (
        string $state,
    ): array {
        return match ($state) {
            'running' => [
                'label' => 'در حال اجرا',
                'badge' => 'border-success/20 bg-success/10 text-success',
                'dot' => 'bg-success',
            ],

            'restarting' => [
                'label' => 'راه‌اندازی مجدد',
                'badge' => 'border-warning/20 bg-warning/10 text-warning',
                'dot' => 'bg-warning',
            ],

            'paused' => [
                'label' => 'متوقف موقت',
                'badge' => 'border-warning/20 bg-warning/10 text-warning',
                'dot' => 'bg-warning',
            ],

            'created' => [
                'label' => 'ایجاد شده',
                'badge' => 'border-info/20 bg-info/10 text-info',
                'dot' => 'bg-info',
            ],

            'exited' => [
                'label' => 'متوقف',
                'badge' => 'border-base-300 bg-base-200 text-base-content/55',
                'dot' => 'bg-base-content/30',
            ],

            'dead' => [
                'label' => 'خطا',
                'badge' => 'border-error/20 bg-error/10 text-error',
                'dot' => 'bg-error',
            ],

            default => [
                'label' => $state !== ''
                    ? $state
                    : 'نامشخص',

                'badge' => 'border-base-300 bg-base-200 text-base-content/55',
                'dot' => 'bg-base-content/30',
            ],
        };
    };
@endphp


<x-dashboard.card
    title="Docker"
    subtitle="وضعیت Docker و کانتینرهای سرور"
    icon="lucide.container"
>
    {{-- Counters --}}
    <x-slot:menu>
        @if(
            $installed
            && $accessible
        )
            <div
                class="
                    flex
                    items-center gap-1.5
                "
            >
                @if($runningCount > 0)
                    <span
                        class="
                            inline-flex
                            items-center gap-1.5

                            rounded-full

                            border border-success/20
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

                        {{ $runningCount }}
                    </span>
                @endif


                <span
                    class="
                        inline-flex
                        min-w-6
                        items-center justify-center

                        rounded-full

                        border border-base-300
                        bg-base-200/60

                        px-2 py-0.5

                        text-[10px]
                        font-medium
                        text-base-content/55
                    "
                >
                    {{ $containerCount }}
                </span>
            </div>
        @endif
    </x-slot:menu>


    @if(! $installed)
        {{-- Docker is not installed --}}
        <div
            class="
                py-7
                text-center
            "
        >
            <div
                class="
                    mx-auto

                    flex size-10
                    items-center justify-center

                    rounded-xl
                    bg-base-200/60

                    text-base-content/35
                "
            >
                <x-icon
                    name="lucide.container"
                    class="!size-4 stroke-[1.7]"
                />
            </div>

            <h3
                class="
                    mt-3

                    text-sm
                    font-medium
                    text-base-content
                "
            >
                Docker نصب نیست
            </h3>

            <p
                class="
                    mx-auto mt-1
                    max-w-xs

                    text-xs
                    leading-6
                    text-base-content/45
                "
            >
                Docker روی این سرور نصب نشده است.
            </p>
        </div>


    @elseif(! $accessible)
        {{-- Docker exists but cannot be inspected --}}
        <div
            class="
                rounded-xl

                border border-warning/20
                bg-warning/[0.04]

                p-4
            "
        >
            <div
                class="
                    flex
                    items-start gap-3
                "
            >
                <div
                    class="
                        flex size-9 shrink-0
                        items-center justify-center

                        rounded-lg
                        bg-warning/10
                        text-warning
                    "
                >
                    <x-icon
                        name="lucide.triangle-alert"
                        class="!size-4 stroke-[1.7]"
                    />
                </div>

                <div class="min-w-0">
                    <h3
                        class="
                            text-sm
                            font-medium
                            text-base-content
                        "
                    >
                        Docker در دسترس نیست
                    </h3>

                    <p
                        class="
                            mt-1

                            text-xs
                            leading-6
                            text-base-content/50
                        "
                    >
                        Docker نصب است، اما daemon در حال اجرا نیست
                        یا کاربر SSH مجوز دسترسی به آن را ندارد.
                    </p>
                </div>
            </div>
        </div>


    @else
        {{-- Docker overview --}}
        @if($containerCount > 0)
            <div
                class="
                    mb-3

                    grid grid-cols-2

                    divide-x
                    divide-x-reverse
                    divide-base-300/70

                    rounded-xl

                    border border-base-300/70
                    bg-base-200/25

                    py-2.5
                "
            >
                <div
                    class="
                        px-3
                        text-center
                    "
                >
                    <div
                        dir="ltr"
                        class="
                            technical-value

                            text-sm
                            font-semibold
                            text-success
                        "
                    >
                        {{ $runningCount }}
                    </div>

                    <div
                        class="
                            mt-0.5

                            text-[10px]
                            text-base-content/40
                        "
                    >
                        در حال اجرا
                    </div>
                </div>


                <div
                    class="
                        px-3
                        text-center
                    "
                >
                    <div
                        dir="ltr"
                        class="
                            technical-value

                            text-sm
                            font-semibold
                            text-base-content
                        "
                    >
                        {{ $containerCount }}
                    </div>

                    <div
                        class="
                            mt-0.5

                            text-[10px]
                            text-base-content/40
                        "
                    >
                        کانتینر
                    </div>
                </div>
            </div>
        @endif


        {{-- Containers viewport --}}
        <div
            class="
                dashboard-scroll

                max-h-72
                min-w-0

                space-y-2

                overflow-y-auto
                overscroll-contain

                pe-1

                [scrollbar-gutter:stable]
            "
        >
            @forelse($containers as $container)
                @php
                    $name = (string) (
                        $container['name']
                        ?? 'unknown'
                    );

                    $image = (string) (
                        $container['image']
                        ?? ''
                    );

                    $state = strtolower(
                        (string) (
                            $container['state']
                            ?? 'unknown'
                        ),
                    );

                    $status = (string) (
                        $container['status']
                        ?? ''
                    );

                    $ports = (string) (
                        $container['ports']
                        ?? ''
                    );

                    $presentation = $statePresentation(
                        $state,
                    );
                @endphp


                <article
                    wire:key="docker-container-{{ md5($name) }}"
                    class="
                        rounded-xl

                        border border-base-300/80
                        bg-base-100

                        px-3 py-2.5
                    "
                >
                    <div
                        class="
                            flex min-w-0
                            items-start justify-between
                            gap-3
                        "
                    >
                        {{-- Container identity --}}
                        <div
                            class="
                                flex min-w-0
                                items-start gap-2.5
                            "
                        >
                            <div
                                class="
                                    mt-0.5

                                    flex size-7 shrink-0
                                    items-center justify-center

                                    rounded-lg
                                    bg-base-200/60
                                "
                            >
                                <span
                                    class="
                                        size-2
                                        rounded-full

                                        {{ $presentation['dot'] }}
                                    "
                                ></span>
                            </div>


                            <div class="min-w-0">
                                <p
                                    dir="ltr"
                                    class="
                                        technical-value

                                        truncate
                                        text-left

                                        text-xs
                                        font-medium
                                        text-base-content

                                        sm:text-sm
                                    "
                                >
                                    {{ $name }}
                                </p>


                                @if($image !== '')
                                    <p
                                        dir="ltr"
                                        title="{{ $image }}"
                                        class="
                                            technical-value

                                            mt-0.5
                                            truncate
                                            text-left

                                            text-[10px]
                                            text-base-content/35
                                        "
                                    >
                                        {{ $image }}
                                    </p>
                                @endif
                            </div>
                        </div>


                        {{-- State --}}
                        <span
                            class="
                                inline-flex
                                shrink-0
                                items-center

                                rounded-full

                                border

                                px-2 py-0.5

                                text-[10px]
                                font-medium

                                {{ $presentation['badge'] }}
                            "
                        >
                            {{ $presentation['label'] }}
                        </span>
                    </div>


                    @if(
                        $status !== ''
                        || $ports !== ''
                    )
                        <div
                            class="
                                mt-2.5
                                space-y-1.5

                                border-t border-base-300/60

                                pt-2.5
                            "
                        >
                            @if($status !== '')
                                <div
                                    class="
                                        flex
                                        items-start gap-2
                                    "
                                >
                                    <x-icon
                                        name="lucide.clock-3"
                                        class="
                                            mt-0.5

                                            !size-3
                                            shrink-0

                                            text-base-content/30
                                            stroke-[1.6]
                                        "
                                    />

                                    <p
                                        dir="ltr"
                                        class="
                                            technical-value

                                            min-w-0
                                            truncate
                                            text-left

                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        {{ $status }}
                                    </p>
                                </div>
                            @endif


                            @if($ports !== '')
                                <div
                                    class="
                                        flex
                                        items-start gap-2
                                    "
                                >
                                    <x-icon
                                        name="lucide.network"
                                        class="
                                            mt-0.5

                                            !size-3
                                            shrink-0

                                            text-base-content/30
                                            stroke-[1.6]
                                        "
                                    />

                                    <p
                                        dir="ltr"
                                        title="{{ $ports }}"
                                        class="
                                            technical-value

                                            min-w-0
                                            truncate
                                            text-left

                                            text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        {{ $ports }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>


            @empty
                <div
                    class="
                        py-8
                        text-center
                    "
                >
                    <div
                        class="
                            mx-auto

                            flex size-10
                            items-center justify-center

                            rounded-xl
                            bg-base-200/60

                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.box"
                            class="!size-4 stroke-[1.7]"
                        />
                    </div>

                    <h3
                        class="
                            mt-3

                            text-sm
                            font-medium
                            text-base-content
                        "
                    >
                        کانتینری وجود ندارد
                    </h3>

                    <p
                        class="
                            mt-1

                            text-xs
                            text-base-content/45
                        "
                    >
                        Docker فعال است اما هنوز کانتینری ایجاد نشده است.
                    </p>
                </div>
            @endforelse
        </div>
    @endif
</x-dashboard.card>
