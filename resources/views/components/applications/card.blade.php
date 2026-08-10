@props([
    'application',
    'serverId',
])
@php
    $state = $application['state'] ?? 'unknown';

    $statusLabel = match ($state) {
        'running' => 'در حال اجرا',
        'installed' => 'نصب‌شده',
        'not_installed' => 'نصب نشده',
        default => 'وضعیت نامشخص',
    };

    $statusClasses = match ($state) {
        'running' => 'border-success/20 bg-success/10 text-success',
        'installed' => 'border-info/20 bg-info/10 text-info',
        'not_installed' => 'border-base-300 bg-base-200 text-base-content/60',
        default => 'border-warning/20 bg-warning/10 text-warning',
    };

    $statusIcon = match ($state) {
        'running' => 'o-play',
        'installed' => 'o-check',
        'not_installed' => 'o-arrow-down-tray',
        default => 'o-exclamation-triangle',
    };
@endphp

<a
    href="{{ route('panel.servers.applications.show', [
    'server' => $serverId,
    'application' => $application['type'],
]) }}"
>
    <x-card
        class="border border-base-300 bg-base-100 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg"
    >
        <div class="flex items-center gap-4">

            <div
                class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 transition-colors duration-200 group-hover:bg-primary/15"
            >
                <x-icon
                    name="o-cube"
                    class="size-6 text-primary"
                />
            </div>

            <div class="min-w-0 flex-1">

                <div class="flex flex-wrap items-center gap-2">

                    <h2 class="truncate text-lg font-semibold text-base-content">
                        {{ $application['name'] }}
                    </h2>

                    <span
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClasses }}"
                    >
                        <x-icon
                            :name="$statusIcon"
                            class="size-3.5"
                        />

                        {{ $statusLabel }}
                    </span>

                </div>

                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/60">

                    <span>
                        {{ $application['type'] }}
                    </span>

                    @if ($application['version'])
                        <span class="size-1 rounded-full bg-base-content/30"></span>

                        <span dir="ltr">
                            v{{ $application['version'] }}
                        </span>
                    @endif

                </div>

            </div>

            <x-icon
                name="o-chevron-left"
                class="size-5 shrink-0 text-base-content/40 transition-transform duration-200 group-hover:-translate-x-1 group-hover:text-primary"
            />

        </div>
    </x-card>
</a>
