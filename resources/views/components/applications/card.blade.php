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
        '
            group
            relative

            block
            overflow-hidden

            rounded-2xl

            border border-base-300/80
            bg-base-100

            p-4

            transition-all
            duration-200

            hover:-translate-y-px
            hover:border-primary/25
            hover:bg-base-200/15

            hover:shadow-md
            hover:shadow-base-content/[0.025]

            focus-visible:outline-none
            focus-visible:ring-2
            focus-visible:ring-primary/25
        ',
    ]) }}
>
    {{-- Soft accent --}}
    <div
        aria-hidden="true"
        class="
            pointer-events-none

            absolute
            -end-12 -top-12

            size-28

            rounded-full
            bg-primary/[0.04]
            blur-2xl
        "
    ></div>


    <div
        class="
            relative

            flex
            items-start
            gap-3.5
        "
    >
        {{-- Icon --}}
        <div
            class="
                flex size-11 shrink-0
                items-center justify-center

                overflow-hidden
                rounded-xl

                border border-primary/10
                bg-primary/[0.055]

                text-primary

                transition-colors
                duration-200

                group-hover:border-primary/20
                group-hover:bg-primary/[0.08]
            "
        >
            @if($usesLucideIcon)
                <x-icon
                    :name="$icon"
                    class="!size-5 stroke-[1.7]"
                />

            @elseif($icon !== null)
                <img
                    src="{{ asset($icon) }}"
                    alt=""
                    class="size-7 object-contain"
                />

            @else
                <x-icon
                    name="lucide.package"
                    class="
                        !size-5
                        stroke-[1.7]
                        text-primary/70
                    "
                />
            @endif
        </div>


        {{-- Content --}}
        <div class="min-w-0 flex-1">
            <div
                class="
                    flex
                    min-w-0
                    items-center gap-2
                "
            >
                <h3
                    class="
                        min-w-0 flex-1
                        truncate

                        text-sm
                        font-semibold
                        tracking-tight
                        text-base-content

                        sm:text-base
                    "
                >
                    {{ $name }}
                </h3>


                <span
                    class="
                        inline-flex
                        shrink-0
                        items-center gap-1.5

                        rounded-full
                        bg-success/[0.08]

                        px-2 py-0.5

                        text-[9px]
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

                    در دسترس
                </span>
            </div>


            @if($shortDescription !== '')
                <p
                    class="
                        mt-1.5

                        line-clamp-2

                        text-xs
                        leading-6
                        text-base-content/50

                        sm:text-sm
                    "
                >
                    {{ $shortDescription }}
                </p>
            @endif


            {{-- Action --}}
            <div
                class="
                    mt-3

                    flex
                    items-center justify-between

                    border-t border-base-300/60

                    pt-2.5
                "
            >
                <span
                    class="
                        text-[10px]
                        text-base-content/35
                    "
                >
                    نصب و مدیریت برنامه
                </span>

                <span
                    class="
                        inline-flex
                        items-center gap-1.5

                        text-[11px]
                        font-medium
                        text-primary
                    "
                >
                    مشاهده

                    <x-icon
                        name="lucide.arrow-left"
                        class="
                            !size-3.5
                            stroke-[1.8]

                            transition-transform
                            duration-200

                            group-hover:-translate-x-0.5
                        "
                    />
                </span>
            </div>
        </div>
    </div>
</a>
