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

    $scrollThreshold = 5;
@endphp

<x-dashboard.card
    title="Docker"
    subtitle="وضعیت Docker و کانتینرهای سرور"
    icon="lucide.container"
>
    <x-slot:menu>
        @if (
            $installed
            && $accessible
        )

            <div class="flex items-center gap-1.5">

                @if ($runningCount > 0)

                    <span
                        class="inline-flex items-center gap-1.5
                               rounded-full border border-success/20
                               bg-success/10 px-2.5 py-1
                               text-xs font-medium text-success"
                    >
                        <span
                            class="size-1.5 rounded-full bg-success"
                        ></span>

                        {{ $runningCount }}
                    </span>

                @endif

                <span
                    class="inline-flex items-center
                           rounded-full border border-base-300
                           bg-base-200/60 px-2.5 py-1
                           text-xs font-medium text-base-content/60"
                >
                    {{ $containerCount }}
                </span>

            </div>

        @endif
    </x-slot:menu>

    @if (! $installed)

        {{-- Docker is not installed --}}
        <div class="py-8 text-center">

            <div
                class="mx-auto flex size-10
                       items-center justify-center
                       rounded-xl bg-base-200/60"
            >
                <x-icon
                    name="lucide.container"
                    class="size-4.5 text-base-content/35"
                />
            </div>

            <h3
                class="mt-3 text-sm font-medium
                       text-base-content"
            >
                Docker نصب نیست
            </h3>

            <p
                class="mx-auto mt-1.5 max-w-xs
                       text-xs leading-6
                       text-base-content/50"
            >
                Docker روی این سرور نصب نشده است.
            </p>

        </div>

    @elseif (! $accessible)

        {{-- Docker exists but cannot be inspected --}}
        <div
            class="rounded-xl border border-warning/20
                   bg-warning/5 p-4"
        >
            <div class="flex items-start gap-3">

                <div
                    class="flex size-9 shrink-0
                           items-center justify-center
                           rounded-lg bg-warning/10"
                >
                    <x-icon
                        name="lucide.triangle-alert"
                        class="size-4 text-warning"
                    />
                </div>

                <div class="min-w-0">

                    <h3
                        class="text-sm font-medium
                               text-base-content"
                    >
                        Docker در دسترس نیست
                    </h3>

                    <p
                        class="mt-1 text-xs leading-6
                               text-base-content/55"
                    >
                        Docker نصب است، اما daemon در حال اجرا نیست
                        یا کاربر SSH مجوز دسترسی به آن را ندارد.
                    </p>

                </div>

            </div>
        </div>

    @else

        {{-- Docker overview --}}
        @if ($containerCount > 0)

            <div
                class="mb-5 grid grid-cols-2
                       divide-x divide-x-reverse divide-base-300
                       rounded-xl border border-base-300
                       bg-base-200/30 py-3"
            >
                <div class="px-3 text-center">

                    <p
                        dir="ltr"
                        class="technical-value text-sm
                               font-semibold text-success"
                    >
                        {{ $runningCount }}
                    </p>

                    <p
                        class="mt-1 text-xs
                               text-base-content/45"
                    >
                        در حال اجرا
                    </p>

                </div>

                <div class="px-3 text-center">

                    <p
                        dir="ltr"
                        class="technical-value text-sm
                               font-semibold text-base-content"
                    >
                        {{ $containerCount }}
                    </p>

                    <p
                        class="mt-1 text-xs
                               text-base-content/45"
                    >
                        کانتینر
                    </p>

                </div>
            </div>

        @endif

        {{-- Containers --}}
        <div
            @class([
                'min-w-0 space-y-2',
                'max-h-[28rem] overflow-y-auto overscroll-contain pe-1 [scrollbar-gutter:stable]'
                    => $containerCount > $scrollThreshold,
            ])
        >
            @forelse ($containers as $container)

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
                    class="rounded-xl border border-base-300
                           bg-base-100 px-3.5 py-3"
                    wire:key="docker-container-{{ md5($name) }}"
                >
                    <div
                        class="flex min-w-0
                               items-start justify-between gap-3"
                    >
                        {{-- Container information --}}
                        <div class="flex min-w-0 items-start gap-3">

                            <div
                                class="mt-0.5 flex size-8 shrink-0
                                       items-center justify-center
                                       rounded-lg bg-base-200/60"
                            >
                                <span
                                    class="size-2 rounded-full
                                           {{ $presentation['dot'] }}"
                                ></span>
                            </div>

                            <div class="min-w-0">

                                <p
                                    dir="ltr"
                                    class="technical-value truncate
                                           text-left text-sm font-medium
                                           text-base-content"
                                >
                                    {{ $name }}
                                </p>

                                @if ($image !== '')

                                    <p
                                        dir="ltr"
                                        class="technical-value mt-0.5
                                               truncate text-left text-xs
                                               text-base-content/40"
                                    >
                                        {{ $image }}
                                    </p>

                                @endif

                            </div>

                        </div>

                        {{-- State --}}
                        <span
                            class="inline-flex shrink-0 items-center
                                   rounded-full border px-2.5 py-1
                                   text-xs font-medium
                                   {{ $presentation['badge'] }}"
                        >
                            {{ $presentation['label'] }}
                        </span>

                    </div>

                    @if (
                        $status !== ''
                        || $ports !== ''
                    )

                        <div
                            class="mt-3 space-y-2
                                   border-t border-base-300 pt-3"
                        >

                            @if ($status !== '')

                                <div
                                    class="flex items-start gap-2"
                                >
                                    <x-icon
                                        name="lucide.clock-3"
                                        class="mt-0.5 size-3.5
                                               shrink-0
                                               text-base-content/35"
                                    />

                                    <p
                                        dir="ltr"
                                        class="technical-value min-w-0
                                               truncate text-left text-xs
                                               text-base-content/45"
                                    >
                                        {{ $status }}
                                    </p>
                                </div>

                            @endif

                            @if ($ports !== '')

                                <div
                                    class="flex items-start gap-2"
                                >
                                    <x-icon
                                        name="lucide.network"
                                        class="mt-0.5 size-3.5
                                               shrink-0
                                               text-base-content/35"
                                    />

                                    <p
                                        dir="ltr"
                                        class="technical-value min-w-0
                                               truncate text-left text-xs
                                               text-base-content/45"
                                        title="{{ $ports }}"
                                    >
                                        {{ $ports }}
                                    </p>
                                </div>

                            @endif

                        </div>

                    @endif

                </article>

            @empty

                <div class="py-8 text-center">

                    <div
                        class="mx-auto flex size-10
                               items-center justify-center
                               rounded-xl bg-base-200/60"
                    >
                        <x-icon
                            name="lucide.box"
                            class="size-4.5 text-base-content/35"
                        />
                    </div>

                    <h3
                        class="mt-3 text-sm font-medium
                               text-base-content"
                    >
                        کانتینری وجود ندارد
                    </h3>

                    <p
                        class="mt-1.5 text-xs
                               text-base-content/50"
                    >
                        Docker فعال است اما هنوز کانتینری ایجاد نشده است.
                    </p>

                </div>

            @endforelse
        </div>

    @endif
</x-dashboard.card>
