@props([
    'title' => null,
])

@php
    $showBuyVpsCta = auth()->check()
        && ! auth()->user()->hasActiveCloudServer();
@endphp

<header
    {{ $attributes->class([
        'relative z-40',
        'flex h-12 items-center justify-between',
        'rounded-2xl',
        'border border-base-300/60',
        'bg-base-100/[0.92]',
        'px-3 sm:px-4 lg:px-5',
        'shadow-sm shadow-base-content/[0.015]',
        'backdrop-blur-xl',
    ]) }}
>
    {{-- Mobile context + contextual CTA --}}
    <div class="flex min-w-0 items-center gap-2.5">

        {{-- Drawer trigger --}}
        <label
            for="panel-drawer"
            aria-label="باز کردن منوی پنل"
            class="btn btn-square btn-ghost btn-sm
                   rounded-xl
                   text-base-content/55
                   hover:bg-base-200/60
                   hover:text-base-content
                   lg:hidden"
        >
            <x-icon
                name="lucide.menu"
                class="!size-5 stroke-[1.8]"
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

        @if ($showBuyVpsCta)
            <a
                data-panel-buy-vps-cta
                href="{{ route('panel.servers.buy') }}"
                wire:navigate
                class="btn btn-soft btn-accent btn-sm
                       h-8 min-h-8
                       cursor-pointer rounded-xl
                       px-3 text-xs font-semibold
                       shadow-none"
            >
                <x-icon
                    name="lucide.cloud"
                    class="!size-3.5 stroke-[1.8]"
                />

                <span>خرید VPS</span>
            </a>
        @endif

    </div>

    {{-- Utilities --}}
    <div
        class="flex shrink-0
               items-center gap-1"
    >
        <livewire:notifications.bell />

        <div
            class="tooltip tooltip-bottom
                   before:z-50 before:text-xs
                   after:z-50"
            data-tip="تغییر پوسته"
        >
            <x-theme-toggle
                class="btn btn-square btn-ghost btn-sm
                       rounded-xl
                       text-base-content/50
                       hover:bg-base-200/60
                       hover:text-base-content"
            />
        </div>

        <div
            class="mx-1 hidden h-5 w-px
                   bg-base-content/10 sm:block"
            aria-hidden="true"
        ></div>

        <x-panel.user-menu />
    </div>
</header>
