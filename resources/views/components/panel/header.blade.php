@props([
    'title' => null,
])

<header
    class="
        sticky top-0 z-30

        flex h-14
        items-center justify-between

        border-b border-base-300
        bg-base-100/90

        px-3
        backdrop-blur-xl

        sm:px-4
        lg:px-6
    "
>
    {{-- Start --}}
    <div class="flex min-w-0 items-center gap-3">

        <label
            for="panel-drawer"
            aria-label="باز کردن منوی پنل"
            class="
                btn btn-square btn-ghost btn-sm
                lg:hidden
            "
        >
            <x-icon
                name="lucide.menu"
                class="size-5"
            />
        </label>

        @if($title)
            <span
                class="
                    truncate
                    text-sm font-medium
                    text-base-content/70
                "
            >
                {{ $title }}
            </span>
        @endif

    </div>


    {{-- End --}}
    <div class="flex items-center gap-1">

        <x-theme-toggle
            class="btn btn-square btn-ghost btn-sm"
        />

        <x-panel.user-menu />

    </div>
</header>
