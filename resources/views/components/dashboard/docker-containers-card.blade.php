@props([
    'docker' => [],
])

@php
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
            $docker['containers'],
        )
        : [];

    $runningCount = (int) (
        $docker['running_count']
        ?? count(array_filter(
            $containers,
            static fn (array $container): bool =>
                ($container['state'] ?? null)
                === 'running',
        ))
    );

    $statePresentation = static fn (
        string $state,
    ): array => match ($state) {
        'running' => [
            'label' => 'در حال اجرا',
            'badge' => 'badge-success',
        ],

        'restarting' => [
            'label' => 'راه‌اندازی مجدد',
            'badge' => 'badge-warning',
        ],

        'paused' => [
            'label' => 'متوقف موقت',
            'badge' => 'badge-warning',
        ],

        'created' => [
            'label' => 'ایجاد شده',
            'badge' => 'badge-info',
        ],

        'exited' => [
            'label' => 'متوقف',
            'badge' => 'badge-ghost',
        ],

        'dead' => [
            'label' => 'خطا',
            'badge' => 'badge-error',
        ],

        default => [
            'label' => $state !== ''
                ? $state
                : 'نامشخص',
            'badge' => 'badge-neutral',
        ],
    };
@endphp

<div class="w-full min-w-0">
    <x-dashboard.card
        title="کانتینرهای Docker"
        subtitle="Docker Containers"
        icon="lucide.container"
        class="w-full min-w-0"
    >
        @if (! $installed)

            <div class="py-7 text-center">
                <div
                    class="mx-auto flex size-11 items-center
                           justify-center rounded-2xl bg-base-200"
                >
                    <x-icon
                        name="lucide.container"
                        class="size-5 text-base-content/30"
                    />
                </div>

                <p class="mt-3 text-sm text-base-content/50">
                    Docker روی این سرور نصب نیست.
                </p>
            </div>

        @elseif (! $accessible)

            <div
                class="rounded-2xl border border-warning/15
                       bg-warning/5 p-4"
            >
                <div class="flex items-start gap-3">
                    <x-icon
                        name="o-exclamation-triangle"
                        class="mt-0.5 size-5 shrink-0 text-warning"
                    />

                    <div>
                        <div class="text-sm font-medium">
                            Docker در دسترس نیست
                        </div>

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

            <div class="mb-5 flex flex-wrap items-center gap-2">
                <span class="badge badge-success badge-sm">
                    {{ $runningCount }} در حال اجرا
                </span>

                <span class="badge badge-ghost badge-sm">
                    {{ count($containers) }} کانتینر
                </span>
            </div>

            <div
                class="max-h-80 space-y-3 overflow-y-auto
                       overflow-x-hidden scrollbar-thin"
            >
                @forelse ($containers as $container)

                    @php
                        $state = strtolower(
                            (string) (
                                $container['state']
                                ?? 'unknown'
                            ),
                        );

                        $presentation =
                            $statePresentation(
                                $state,
                            );
                    @endphp

                    <div
                        class="rounded-2xl border border-base-content/5
                               bg-base-200/30 p-3"
                        wire:key="docker-container-{{ md5((string) ($container['name'] ?? '')) }}"
                    >
                        <div
                            class="flex items-start
                                   justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div
                                    dir="ltr"
                                    class="truncate text-left
                                           text-sm font-medium"
                                >
                                    {{ $container['name'] ?? 'unknown' }}
                                </div>

                                <div
                                    dir="ltr"
                                    class="mt-1 truncate text-left
                                           font-mono text-[10px]
                                           text-base-content/45"
                                >
                                    {{ $container['image'] ?? '' }}
                                </div>
                            </div>

                            <span
                                class="badge badge-sm shrink-0
                                       {{ $presentation['badge'] }}"
                            >
                                {{ $presentation['label'] }}
                            </span>
                        </div>

                        @if (! empty($container['status']))
                            <div
                                dir="ltr"
                                class="mt-2 truncate text-left
                                       text-[11px]
                                       text-base-content/50"
                            >
                                {{ $container['status'] }}
                            </div>
                        @endif

                        @if (! empty($container['ports']))
                            <div
                                dir="ltr"
                                class="mt-2 truncate rounded-lg
                                       bg-base-200 px-2 py-1
                                       text-left font-mono text-[10px]
                                       text-base-content/50"
                            >
                                {{ $container['ports'] }}
                            </div>
                        @endif
                    </div>

                @empty

                    <div class="py-7 text-center">
                        <p class="text-sm text-base-content/50">
                            Docker فعال است، اما کانتینری وجود ندارد.
                        </p>
                    </div>

                @endforelse
            </div>

        @endif
    </x-dashboard.card>
</div>
