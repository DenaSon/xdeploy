@props([
    'title' => config('app.name'),
    'subtitle' => 'Deploy & Manage VPS',
    'icon' => 'lucide.rocket',
])

<div
    class="
        border-b
        border-base-content/5
        px-5
        py-5
    "
>

    <a
        href="{{ route('panel.dashboard') }}"
        wire:navigate
        class="
            group
            flex
            items-center
            gap-3
        "
    >

        <div
            class="
                flex
                size-10
                shrink-0
                items-center
                justify-center

                rounded-2xl

                bg-primary
                text-primary-content

                shadow-sm

                transition-all
                duration-300

                group-hover:scale-[1.03]
                group-hover:shadow-md
            "
        >
            <x-icon
                :name="$icon"
                class="size-3"
            />
        </div>

        <div class="min-w-0">

            <h1
                class="
                    text-base
                    font-bold
                    leading-none
                    tracking-tight
                "
            >
                {{ $title }}
            </h1>

            @if($subtitle)
                <p
                    class="
                        mt-1
                        truncate
                        text-xs
                        text-base-content/55
                    "
                >
                    {{ $subtitle }}
                </p>
            @endif

        </div>

    </a>

</div>
