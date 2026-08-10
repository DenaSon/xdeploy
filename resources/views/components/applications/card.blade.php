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

    $description = $application['description']
        ?? null;

    $description = is_string($description)
        && trim($description) !== ''
            ? trim($description)
            : null;

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

<article
    {{ $attributes->class([
        'group relative flex h-full flex-col overflow-hidden',
        'rounded-2xl border border-base-300 bg-base-100',
        'shadow-[0_4px_18px_rgba(15,23,42,0.035)]',
        'transition-all duration-200',
        'hover:-translate-y-0.5',
        'hover:border-primary/30',
        'hover:shadow-[0_12px_30px_rgba(15,23,42,0.07)]',
    ]) }}
    x-data="{ expanded: false }"
>
    {{-- Top accent --}}
    <div
        class="absolute inset-x-0 top-0
               h-1 bg-primary"
    ></div>

    {{-- Application identity --}}
    <div
        class="relative border-b border-base-300
               px-4 pb-3.5 pt-4.5"
    >
        <div
            class="flex min-w-0
                   items-center gap-3.5"
        >
            {{-- Application logo --}}
            <div
                class="flex size-13 shrink-0
                       items-center justify-center
                       overflow-hidden rounded-xl
                       border border-primary/15
                       bg-primary/[0.06]
                       transition-colors duration-200
                       group-hover:bg-primary/[0.09]"
            >
                @if ($usesLucideIcon)

                    <x-icon
                        :name="$icon"
                        class="size-6 text-primary"
                    />

                @elseif ($icon !== null)

                    <img
                        src="{{ asset($icon) }}"
                        alt="{{ $name }}"
                        class="size-9 object-contain"
                    />


                @else

                    <x-icon
                        name="lucide.package"
                        class="size-6 text-primary/60"
                    />

                @endif
            </div>

            {{-- Name and support --}}
            <div
                class="min-w-0 flex-1
                       text-right"
            >
                <h3
                    class="truncate
                           text-base font-semibold
                           leading-6
                           text-base-content"
                >
                    {{ $name }}
                </h3>

                <div
                    class="mt-1.5 flex
                           items-center justify-start"
                >
                    <span
                        class="inline-flex items-center
                               gap-1.5 rounded-full
                               bg-success/10
                               px-1 py-1
                               text-[11px] font-medium
                               text-success"
                    >
                        <x-icon
                            name="lucide.badge-check"
                            class="size-1.5"
                        />


                    </span>
                </div>
            </div>

            {{-- Information --}}
            @if ($description !== null)

                <div
                    class="tooltip tooltip-top
           before:z-50 before:whitespace-nowrap before:text-xs
           after:z-50"
                    data-tip="اطلاعات"
                >
                    <button
                        type="button"
                        @click="expanded = ! expanded"
                        x-bind:aria-expanded="expanded.toString()"
                        x-bind:class="{
            'border-primary/20 bg-primary/10 text-primary':
                expanded,
        }"
                        aria-label="اطلاعات بیشتر درباره {{ $name }}"
                        class="flex size-9 shrink-0
               items-center justify-center
               rounded-xl
               border border-base-300
               bg-base-100
               text-base-content/40
               transition-all duration-150
               hover:border-primary/20
               hover:bg-primary/5
               hover:text-primary"
                    >
                        <x-icon
                            name="lucide.info"
                            class="size-4"
                        />
                    </button>
                </div>

            @endif
        </div>
    </div>

    {{-- Application summary --}}
    <div
        class="flex flex-1 flex-col
               px-4 py-3.5"
    >
        @if ($shortDescription !== '')

            <p
                class="line-clamp-2
                       text-sm leading-6
                       text-base-content/60"
            >
                {{ $shortDescription }}
            </p>

        @endif

        {{-- Extended information --}}
        @if ($description !== null)

            <div
                x-cloak
                x-show="expanded"
                x-collapse
            >
                <div
                    class="mt-3
                           border-r-2 border-primary/35
                           pr-3"
                >
                    <div
                        class="mb-1 flex items-center
                               gap-1.5
                               text-[11px] font-medium
                               text-base-content/45"
                    >
                        <x-icon
                            name="lucide.info"
                            class="size-3.5"
                        />

                        <span>
                            درباره برنامه
                        </span>
                    </div>

                    <p
                        class="text-xs leading-6
                               text-base-content/55"
                    >
                        {{ $description }}
                    </p>
                </div>
            </div>

        @endif
    </div>

    {{-- Application action --}}
    <a
        href="{{ $showRoute }}"
        wire:navigate
        class="group/action
               flex items-center
               justify-between gap-4
               border-t border-base-300
               bg-base-200/30
               px-4 py-3
               transition-colors duration-150
               hover:bg-primary/[0.06]"
    >
        {{-- Action information --}}
        <div
            class="flex min-w-0
                   items-center gap-2.5"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-primary/8
                       text-primary
                       transition-colors duration-150
                       group-hover/action:bg-primary/12"
            >
                <x-icon
                    name="lucide.sliders-horizontal"
                    class="size-4"
                />
            </div>

            <div class="min-w-0 text-right">

                <p
                    class="text-sm font-semibold
                           text-base-content/80
                           transition-colors duration-150
                           group-hover/action:text-primary"
                >
                    مدیریت برنامه
                </p>

                <p
                    class="mt-0.5 truncate
                           text-[11px]
                           text-base-content/40"
                >
                    نصب، تنظیمات و عملیات
                </p>

            </div>
        </div>

        {{-- Arrow --}}
        <div
            class="flex size-8 shrink-0
                   items-center justify-center
                   rounded-lg
                   border border-base-300
                   bg-base-100
                   text-base-content/40
                   transition-all duration-200
                   group-hover/action:border-primary/20
                   group-hover/action:bg-primary
                   group-hover/action:text-primary-content"
        >
            <x-icon
                name="lucide.arrow-left"
                class="size-3.5
                       transition-transform duration-200
                       group-hover/action:-translate-x-0.5"
            />
        </div>
    </a>
</article>
