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
        'flex min-h-14 items-center justify-between gap-1.5',
        'sm:h-12 sm:min-h-12 sm:gap-2',
        'rounded-2xl',
        'border border-base-300/60',
        'bg-base-100/[0.92]',
        'px-2 sm:px-4 lg:px-5',
        'shadow-sm shadow-base-content/[0.015]',
        'backdrop-blur-xl',
    ]) }}
>
    {{-- Mobile context + contextual CTA --}}
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
                rounded-xl
                text-base-content/55
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

        @if ($showBuyVpsCta)
            <div class="aura min-w-0 text-success/70 bg-success/8">
                <a
                    data-panel-buy-vps-cta
                    href="{{ route('panel.servers.buy') }}"
                    wire:navigate.hover
                    aria-label="خرید و راه‌اندازی VPS"
                    class="
                        btn btn-soft btn-accent
                        h-10 min-h-10
                        cursor-pointer rounded-xl
                        px-2.5 text-xs font-semibold
                        shadow-none
                        sm:h-8 sm:min-h-8 sm:px-3
                    "
                >
                    <x-icon
                        name="lucide.cloud"
                        class="!size-3.5 shrink-0 stroke-[1.8]"
                    />

                    <span class="hidden min-[360px]:inline">
                        راه‌اندازی
                    </span>
                </a>
            </div>
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
                    hover:bg-base-200/60
                    hover:text-base-content
                    sm:h-8 sm:min-h-8 sm:w-8
                "
            />
        </div>

        <div
            class="
                mx-1 hidden h-5 w-px
                bg-base-content/10
                md:block
            "
            aria-hidden="true"
        ></div>

        <x-panel.user-menu />
    </div>
</header>
