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

    $totalCount = count(
        $services,
    );
@endphp


<x-dashboard.card
    title="سرویس‌های سیستم"
    subtitle="وضعیت سرویس‌های شناسایی‌شده توسط systemd"
    icon="lucide.activity"
>
    {{-- Header counters --}}
    <x-slot:menu>
        @if($totalCount > 0)
            <div
                class="
                    flex
                    items-center gap-1.5
                "
            >
                @if($failedCount > 0)
                    <span
                        class="
                            inline-flex
                            items-center gap-1.5

                            rounded-full

                            border border-error/20
                            bg-error/10

                            px-2 py-0.5

                            text-[10px]
                            font-medium
                            text-error
                        "
                    >
                        <span
                            class="
                                size-1.5
                                rounded-full
                                bg-error
                            "
                        ></span>

                        {{ $failedCount }} خطا
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
                    {{ $totalCount }}
                </span>
            </div>
        @endif
    </x-slot:menu>


    @if($totalCount > 0)
        {{-- Overview --}}
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
                    {{ $activeCount }}
                </div>

                <div
                    class="
                        mt-0.5

                        text-[10px]
                        text-base-content/40
                    "
                >
                    فعال
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
                    {{ $totalCount }}
                </div>

                <div
                    class="
                        mt-0.5

                        text-[10px]
                        text-base-content/40
                    "
                >
                    شناسایی‌شده
                </div>
            </div>
        </div>
    @endif


    {{-- Services viewport --}}
    <div
        class="
            dashboard-scroll

            max-h-64
            min-w-0

            space-y-2

            overflow-y-auto
            overscroll-contain

            pe-1

            [scrollbar-gutter:stable]
        "
    >
        @forelse($services as $service)
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
                        name="lucide.activity"
                        class="!size-4 stroke-[1.7]"
                    />
                </div>

                <p
                    class="
                        mt-3

                        text-xs
                        leading-6
                        text-base-content/45
                    "
                >
                    سرویس systemd قابل نمایشی پیدا نشد.
                </p>
            </div>
        @endforelse
    </div>
</x-dashboard.card>
