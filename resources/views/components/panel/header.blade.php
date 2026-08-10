@props([
    'title' => null,
])

<header
    {{ $attributes->class([
        'flex h-12 items-center justify-between',
        'border-b border-base-300',
        'bg-base-100',
        'px-3 sm:px-4 lg:px-5',
    ]) }}
>
    {{-- Mobile context --}}
    <div class="flex min-w-0 items-center gap-2.5">

        {{-- Drawer trigger --}}
        <label
            for="panel-drawer"
            aria-label="باز کردن منوی پنل"
            class="btn btn-square btn-ghost btn-sm
                   lg:hidden"
        >
            <x-icon
                name="lucide.menu"
                class="size-5"
            />
        </label>

        {{-- Page title: mobile only --}}
        @if ($title)
            <span
                class="truncate text-sm font-medium
                       text-base-content/65
                       lg:hidden"
            >
                {{ $title }}
            </span>
        @endif

    </div>

    {{-- Utilities --}}
    <div
        class="flex shrink-0
               items-center gap-1"
    >
        <div
            class="tooltip tooltip-bottom
                   before:z-50 before:text-xs
                   after:z-50"
            data-tip="تغییر پوسته"
        >
            <x-theme-toggle
                class="btn btn-square btn-ghost btn-sm"
            />
        </div>

        <div
            class="mx-1 hidden h-5 w-px
                   bg-base-300 sm:block"
            aria-hidden="true"
        ></div>

        <x-panel.user-menu />
    </div>
</header>
