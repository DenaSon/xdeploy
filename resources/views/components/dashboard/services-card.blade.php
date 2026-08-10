@props([
    'services' => [],
])

@php
    $services = is_array($services)
        ? array_values(
            array_filter(
                $services,
                static fn (mixed $service): bool =>
                    is_array($service)
                    && isset(
                        $service['name'],
                        $service['status'],
                    ),
            ),
        )
        : [];

    /*
     * Presentation priority only.
     *
     * Service existence is never determined here.
     * All services have already been discovered from systemd.
     */
    $priority = [
        'ssh' => 100,
        'sshd' => 100,

        'docker' => 95,

        'nginx' => 90,
        'apache2' => 85,
        'caddy' => 85,

        'fail2ban' => 80,

        'redis-server' => 75,
        'redis' => 75,

        'mysql' => 70,
        'mariadb' => 70,
        'postgresql' => 70,
    ];

    /*
     * Health comes before presentation priority.
     *
     * Failed and transitional services should be visible
     * before healthy and inactive services.
     */
    $healthRank = [
        'failed' => 0,

        'starting' => 1,
        'stopping' => 1,
        'reloading' => 1,

        'active' => 2,

        'inactive' => 3,

        'unknown' => 4,
    ];

    usort(
        $services,
        static function (
            array $left,
            array $right,
        ) use (
            $priority,
            $healthRank,
        ): int {
            $leftStatus = (string) (
                $left['status']
                ?? 'unknown'
            );

            $rightStatus = (string) (
                $right['status']
                ?? 'unknown'
            );

            $healthComparison = (
                $healthRank[$leftStatus]
                ?? 99
            ) <=> (
                $healthRank[$rightStatus]
                ?? 99
            );

            if ($healthComparison !== 0) {
                return $healthComparison;
            }

            $leftName = strtolower(
                (string) $left['name'],
            );

            $rightName = strtolower(
                (string) $right['name'],
            );

            $priorityComparison = (
                $priority[$rightName]
                ?? 0
            ) <=> (
                $priority[$leftName]
                ?? 0
            );

            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strnatcasecmp(
                $leftName,
                $rightName,
            );
        },
    );

    $activeCount = count(
        array_filter(
            $services,
            static fn (array $service): bool =>
                ($service['status'] ?? null) === 'active',
        ),
    );

    $failedCount = count(
        array_filter(
            $services,
            static fn (array $service): bool =>
                ($service['status'] ?? null) === 'failed',
        ),
    );

    $visibleLimit = 8;

    $totalCount = count(
        $services,
    );

    $hiddenCount = max(
        0,
        $totalCount - $visibleLimit,
    );
@endphp

<x-dashboard.card
    title="سرویس‌های سیستم"
    subtitle="وضعیت سرویس‌های شناسایی‌شده توسط systemd"
    icon="lucide.activity"
    x-data="{ expanded: false }"
>
    {{-- Header counters --}}
    <x-slot:menu>
        @if ($totalCount > 0)

            <div class="flex items-center gap-1.5">

                @if ($failedCount > 0)

                    <span
                        class="inline-flex items-center gap-1.5
                               rounded-full border border-error/20
                               bg-error/10 px-2.5 py-1
                               text-xs font-medium text-error"
                    >
                        <span
                            class="size-1.5 rounded-full bg-error"
                        ></span>

                        {{ $failedCount }} خطا
                    </span>

                @endif

                <span
                    class="inline-flex items-center
                           rounded-full border border-base-300
                           bg-base-200/60 px-2.5 py-1
                           text-xs font-medium text-base-content/60"
                >
                    {{ $totalCount }}
                </span>

            </div>

        @endif
    </x-slot:menu>

    @if ($totalCount > 0)

        {{-- Overview --}}
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
                    {{ $activeCount }}
                </p>

                <p
                    class="mt-1 text-xs
                           text-base-content/45"
                >
                    فعال
                </p>

            </div>

            <div class="px-3 text-center">

                <p
                    dir="ltr"
                    class="technical-value text-sm
                           font-semibold text-base-content"
                >
                    {{ $totalCount }}
                </p>

                <p
                    class="mt-1 text-xs
                           text-base-content/45"
                >
                    شناسایی‌شده
                </p>

            </div>
        </div>

    @endif

    {{--
        Services viewport

        When there are more than the visible limit, the viewport keeps
        a fixed height. Expanding the list only enables internal scrolling;
        it never increases the height of the Dashboard card.
    --}}
    <div
        @class([
            'min-w-0 space-y-2',
            'h-[30rem]' => $hiddenCount > 0,
        ])
        x-bind:class="{
            'overflow-y-auto overscroll-contain pe-1 [scrollbar-gutter:stable]':
                expanded,
            'overflow-hidden':
                ! expanded
        }"
    >
        @forelse ($services as $index => $service)

            @php
                $name = (string) (
                    $service['name']
                    ?? 'unknown'
                );

                $status = (string) (
                    $service['status']
                    ?? 'unknown'
                );
            @endphp

            @if ($index < $visibleLimit)

                <div
                    wire:key="system-service-{{ md5($name) }}"
                >
                    <x-dashboard.service-status
                        :name="$name"
                        :status="$status"
                        :description="$service['description'] ?? null"
                        :sub-state="$service['sub_state'] ?? null"
                    />
                </div>

            @else

                <div
                    x-cloak
                    x-show="expanded"
                    x-transition.opacity.duration.150ms
                    wire:key="system-service-hidden-{{ md5($name) }}"
                >
                    <x-dashboard.service-status
                        :name="$name"
                        :status="$status"
                        :description="$service['description'] ?? null"
                        :sub-state="$service['sub_state'] ?? null"
                    />
                </div>

            @endif

        @empty

            <div class="py-8 text-center">

                <div
                    class="mx-auto flex size-10
                           items-center justify-center
                           rounded-xl bg-base-200/60"
                >
                    <x-icon
                        name="lucide.activity"
                        class="size-4.5 text-base-content/35"
                    />
                </div>

                <p
                    class="mt-3 text-sm
                           text-base-content/50"
                >
                    سرویس systemd قابل نمایشی پیدا نشد.
                </p>

            </div>

        @endforelse
    </div>

    {{-- Expand / Collapse --}}
    @if ($hiddenCount > 0)

        <div
            class="mt-4 border-t
                   border-base-300 pt-4"
        >
            <button
                type="button"
                class="flex w-full items-center
                       justify-center gap-2 rounded-xl
                       px-3 py-2 text-sm font-medium
                       text-base-content/55 transition-colors
                       hover:bg-base-200/60
                       hover:text-base-content"
                @click="expanded = ! expanded"
            >
                <span x-show="! expanded">
                    نمایش {{ $hiddenCount }} سرویس دیگر
                </span>

                <span
                    x-cloak
                    x-show="expanded"
                >
                    نمایش کمتر
                </span>

                <x-icon
                    name="lucide.chevron-down"
                    class="size-4 transition-transform
                           duration-200"
                    x-bind:class="{
                        'rotate-180': expanded
                    }"
                />
            </button>
        </div>

    @endif
</x-dashboard.card>
