@props([
    'title' => config('app.name'),
    'subtitle' => 'Deploy & Manage VPS',
    'icon' => 'lucide.rocket',
])

<div
    class="
        overflow-hidden
        border-b border-base-content/5
        px-2.5 py-4
    "
>
    <a
        href="{{ route('panel.servers.index') }}"
        wire:navigate
        title="{{ $title }}"
        class="
            group
            flex min-w-0 items-center gap-3
        "
    >
        {{-- Logo --}}
        <span
            class="
                flex size-10 shrink-0
                items-center justify-center

                rounded-2xl
                bg-primary
                text-primary-content

                shadow-sm shadow-primary/20

                transition-all duration-200

                group-hover:scale-[1.03]
                group-hover:shadow-md
                group-hover:shadow-primary/20
            "
        >
            <x-icon
                :name="$icon"
                class="!size-4 stroke-[1.8]"
            />
        </span>

        {{-- Brand text: hidden automatically when sidebar collapses --}}
        <span class="mary-hideable min-w-0 overflow-hidden">

            <span
                class="
                    block truncate
                    text-base font-bold
                    leading-none tracking-tight
                    text-base-content
                "
            >
                {{ $title }}
            </span>

            @if($subtitle)
                <span
                    class="
                        mt-1.5 block truncate
                        text-[10px] font-normal
                        text-base-content/40
                    "
                >
                    {{ $subtitle }}
                </span>
            @endif

        </span>
    </a>
</div>
