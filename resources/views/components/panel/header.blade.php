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
        'flex min-h-14 items-center justify-between gap-2',
        'sm:h-12 sm:min-h-12',
        'rounded-2xl',
        'border border-base-300/60',
        'bg-base-100/[0.92]',
        'px-2.5 sm:px-4 lg:px-5',
        'shadow-sm shadow-base-content/[0.015]',
        'backdrop-blur-xl',
    ]) }}
>
    {{-- Mobile context --}}
    <div
        class="
            flex min-w-0 flex-1
            items-center gap-1.5
            sm:gap-2.5
        "
    >
        {{-- Drawer trigger --}}
        <label
            for="panel-drawer"
            aria-label="باز کردن منوی پنل"
            class="
                btn btn-square btn-ghost
                h-10 min-h-10 w-10
                shrink-0 rounded-xl
                text-base-content/55
                shadow-none
                hover:bg-base-200/60
                hover:text-base-content
                sm:h-8 sm:min-h-8 sm:w-8
                lg:hidden
            "
        >
            <x-icon
                name="lucide.menu"
                class="!size-5 stroke-[1.8]"
            />
        </label>

        {{-- Page title: mobile only --}}
        @if ($title)
            <span
                class="
                    min-w-0 truncate
                    text-xs font-medium
                    text-base-content/65
                    min-[380px]:text-sm
                    lg:hidden
                "
            >
                {{ $title }}
            </span>
        @endif

        {{-- Contextual VPS CTA --}}
        @if ($showBuyVpsCta)
            <a
                data-panel-buy-vps-cta
                href="{{ route('panel.servers.buy') }}"
                wire:navigate.hover
                aria-label="خرید و راه‌اندازی "
                class="
                    btn btn-soft btn-primary
                    h-10 min-h-10
                    shrink-0
                    cursor-pointer
                    rounded-xl
                    border-transparent
                    px-2.5
                    text-xs font-medium
                    shadow-none
                    transition-colors
                    sm:h-8 sm:min-h-8 sm:px-3
                "
            >
                <x-icon
                    name="lucide.cloud"
                    class="
                        !size-3.5
                        shrink-0
                        stroke-[1.8]
                    "
                />

                <span
                    class="
                        hidden whitespace-nowrap
                        min-[360px]:inline
                    "
                >
                    راه‌اندازی
                </span>
            </a>
        @endif
    </div>

    {{-- Utilities --}}
    <div
        class="
            flex shrink-0
            items-center gap-0.5
            sm:gap-1
        "
    >
        <livewire:notifications.bell />

        <div
            class="
                tooltip tooltip-bottom
                before:z-50 before:text-xs
                after:z-50
            "
            data-tip="تغییر پوسته"
        >
            <x-theme-toggle
                aria-label="تغییر پوسته"
                class="
                    btn btn-square btn-ghost
                    h-10 min-h-10 w-10
                    rounded-xl
                    text-base-content/50
                    shadow-none
                    hover:bg-base-200/60
                    hover:text-base-content
                    sm:h-8 sm:min-h-8 sm:w-8
                "
            />
        </div>

        <div
            aria-hidden="true"
            class="
                mx-1 hidden h-5 w-px
                bg-base-content/10
                md:block
            "
        ></div>

        <x-panel.user-menu />
    </div>
</header>
