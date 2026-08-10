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
@endphp

<article
    {{ $attributes->class([
        'overflow-hidden rounded-2xl',
        'border border-base-300 bg-base-100',
    ]) }}
    x-data="{ expanded: false }"
>
    <div class="p-4 sm:p-5">
        <div class="flex items-start gap-4">
            {{-- Application identity --}}
            <div
                class="flex size-12 shrink-0 items-center
                       justify-center overflow-hidden rounded-xl
                       border border-base-300 bg-base-200/50"
            >
                @if ($usesLucideIcon)
                    <x-icon
                        :name="$icon"
                        class="size-5.5 text-base-content/65"
                    />
                @elseif ($icon !== null)
                    <img
                        src="{{ asset($icon) }}"
                        alt=""
                        class="size-8 object-contain"
                    />
                @else
                    <x-icon
                        name="lucide.package"
                        class="size-5.5 text-base-content/45"
                    />
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3
                            dir="ltr"
                            class="truncate text-left text-base
                                   font-semibold text-base-content"
                        >
                            {{ $name }}
                        </h3>

                        @if ($shortDescription !== '')
                            <p
                                class="mt-1 line-clamp-2
                                       text-sm leading-6
                                       text-base-content/50"
                            >
                                {{ $shortDescription }}
                            </p>
                        @endif
                    </div>

                    @if ($description !== null)
                        <button
                            type="button"
                            class="flex size-8 shrink-0 items-center
                                   justify-center rounded-lg
                                   text-base-content/40 transition-colors
                                   hover:bg-base-200/70
                                   hover:text-base-content"
                            @click="expanded = ! expanded"
                            x-bind:aria-expanded="expanded.toString()"
                            aria-label="اطلاعات بیشتر درباره {{ $name }}"
                        >
                            <x-icon
                                name="lucide.info"
                                class="size-4"
                            />
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if ($description !== null)
            <div
                x-cloak
                x-show="expanded"
                x-transition.opacity.duration.150ms
                class="mt-4 border-t border-base-300 pt-4"
            >
                <p
                    class="text-sm leading-7
                           text-base-content/55"
                >
                    {{ $description }}
                </p>
            </div>
        @endif

        <div
            class="mt-5 flex justify-end"
        >
            <a
                href="{{ route('panel.servers.applications.show', [
                    'server' => $serverId,
                    'application' => $slug,
                ]) }}"
                wire:navigate
                class="inline-flex items-center gap-1.5
                       rounded-xl bg-primary px-3.5 py-2
                       text-sm font-medium text-primary-content
                       transition-opacity hover:opacity-90"
            >
                مشاهده برنامه

                <x-icon
                    name="lucide.chevron-left"
                    class="size-4"
                />
            </a>
        </div>
    </div>
</article>
