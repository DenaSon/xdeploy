@props([
    'application',
    'serverId',
])

@php
    $slug = (string) (
        $application['slug']
        ?? ''
    );

    $name = (string) (
        $application['name']
        ?? $slug
    );

    $shortDescription = (string) (
        $application['short_description']
        ?? ''
    );

    $icon = $application['icon']
        ?? null;

    $icon = is_string($icon)
        && trim($icon) !== ''
            ? trim($icon)
            : null;

    $usesLucideIcon = $icon !== null
        && str_starts_with(
            $icon,
            'lucide.',
        );

    $showRoute = route(
        'panel.servers.applications.show',
        [
            'server' => $serverId,
            'application' => $slug,
        ],
    );
@endphp

<a
    href="{{ $showRoute }}"
    wire:navigate
    aria-label="مشاهده برنامه {{ $name }}"
    {{ $attributes->class([
        'card group h-full overflow-hidden',
        'border border-base-300 bg-base-100',
        'shadow-sm',
        'transition-all duration-200',
        'hover:-translate-y-0.5',
        'hover:border-primary/25',
        'hover:shadow-md',
        'focus-visible:outline-none',
        'focus-visible:ring-2 focus-visible:ring-primary/30',
    ]) }}
>
    <div class="card-body gap-4 p-5">

        {{-- Application identity --}}
        <div class="flex min-w-0 items-start gap-3.5">

            {{-- Application logo --}}
            <div
                class="flex size-12 shrink-0
                       items-center justify-center
                       overflow-hidden rounded-xl
                       border border-base-300
                       bg-base-200/45
                       transition-colors duration-200
                       group-hover:border-primary/20
                       group-hover:bg-primary/[0.06]"
            >
                @if ($usesLucideIcon)

                    <x-icon
                        :name="$icon"
                        class="!size-5.5 text-primary"
                    />

                @elseif ($icon !== null)

                    <img
                        src="{{ asset($icon) }}"
                        alt="{{ $name }}"
                        class="size-8 object-contain"
                    />

                @else

                    <x-icon
                        name="lucide.package"
                        class="!size-5.5 text-primary/70"
                    />

                @endif
            </div>

            {{-- Name and support state --}}
            <div class="min-w-0 flex-1 text-right">

                <div class="flex min-w-0 items-center gap-2">
                    <h3
                        class="min-w-0 flex-1 truncate
                               text-base font-semibold
                               leading-6 text-base-content"
                    >
                        {{ $name }}
                    </h3>

                    <span
                        class="badge badge-success badge-soft badge-xs
                               shrink-0 gap-1 px-1.5
                               text-[10px] font-medium"
                    >
                        <x-icon
                            name="lucide.check"
                            class="!size-2.5"
                        />

                        پشتیبانی‌شده
                    </span>
                </div>

                @if ($shortDescription !== '')

                    <p
                        class="mt-2 line-clamp-2
                               text-sm leading-6
                               text-base-content/55"
                    >
                        {{ $shortDescription }}
                    </p>

                @endif
            </div>
        </div>

        {{-- Application action --}}
        <div
            class="card-actions mt-auto
                   items-center justify-between
                   border-t border-base-300/80
                   pt-3.5"
        >
            <span
                class="text-[11px]
                       text-base-content/35"
            >
                نصب، راه‌اندازی و مدیریت
            </span>

            <span
                class="btn btn-ghost btn-sm
                       pointer-events-none gap-1.5
                       px-2 text-primary
                       group-hover:bg-primary/8"
            >
                مشاهده برنامه

                <x-icon
                    name="lucide.arrow-left"
                    class="!size-3.5
                           transition-transform duration-200
                           group-hover:-translate-x-0.5"
                />
            </span>
        </div>

    </div>
</a>
