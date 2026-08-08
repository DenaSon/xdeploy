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
     * Failed/transitional services should be visible
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
                ($service['status'] ?? null)
                === 'active',
        ),
    );

    $failedCount = count(
        array_filter(
            $services,
            static fn (array $service): bool =>
                ($service['status'] ?? null)
                === 'failed',
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

<div
    class="w-full min-w-0"
    x-data="{ expanded: false }"
>
    <x-dashboard.card
        title="سرویس‌های سیستم"
        subtitle="Systemd Services"
        icon="o-bolt"
        class="w-full min-w-0"
    >
        {{-- Summary --}}
        @if ($services !== [])
            <div
                class="mb-5 flex flex-wrap
                       items-center gap-2"
            >
                <span
                    class="badge badge-success badge-sm"
                >
                    {{ $activeCount }} فعال
                </span>

                @if ($failedCount > 0)
                    <span
                        class="badge badge-error badge-sm"
                    >
                        {{ $failedCount }} خطا
                    </span>
                @endif

                <span
                    class="badge badge-ghost badge-sm"
                >
                    {{ $totalCount }} سرویس
                </span>
            </div>
        @endif

        {{-- Services --}}
        <div
            @class([
                'w-full min-w-0 space-y-3',
                'overflow-x-hidden scrollbar-thin',
                'max-h-[30rem] overflow-y-auto' => $totalCount > $visibleLimit,
            ])
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

                <div
                    class="w-full py-8 text-center"
                >
                    <div
                        class="mx-auto flex size-11
                               items-center justify-center
                               rounded-2xl bg-base-200"
                    >
                        <x-icon
                            name="o-bolt"
                            class="size-5 text-base-content/30"
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
                       border-base-content/5 pt-3"
            >
                <button
                    type="button"
                    class="btn btn-ghost btn-sm w-full"
                    @click="expanded = ! expanded"
                >
                    <span x-show="! expanded">
                        نمایش همه ({{ $totalCount }})
                    </span>

                    <span
                        x-cloak
                        x-show="expanded"
                    >
                        نمایش کمتر
                    </span>
                </button>
            </div>

        @endif
    </x-dashboard.card>
</div>
