@props([
    'title' => null,
])

@php
    $user = auth()->user();
@endphp

<header
    {{ $attributes->class([
        'flex h-12 items-center justify-between',
        'border-b border-base-300',
        'bg-base-100',
        'px-3 sm:px-4 lg:px-5',
    ]) }}
>
    <div class="flex min-w-0 items-center gap-2.5">
        <label
            for="admin-drawer"
            aria-label="باز کردن منوی مدیریت"
            class="btn btn-square btn-ghost btn-sm lg:hidden"
        >
            <x-icon
                name="lucide.menu"
                class="size-5"
            />
        </label>

        @if ($title)
            <span
                class="
                    truncate text-sm font-medium
                    text-base-content/65
                    lg:hidden
                "
            >
                {{ $title }}
            </span>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <div
            class="
                tooltip tooltip-bottom
                before:z-50 before:text-xs
                after:z-50
            "
            data-tip="تغییر پوسته"
        >
            <x-theme-toggle
                class="btn btn-square btn-ghost btn-sm"
            />
        </div>

        <div
            class="mx-1 hidden h-5 w-px bg-base-300 sm:block"
            aria-hidden="true"
        ></div>

        <x-dropdown>
            <x-slot:trigger>
                <x-button
                    icon="lucide.user-cog"
                    class="btn-ghost btn-sm"
                    responsive
                >
                    <div class="hidden text-right lg:block">
                        <div class="font-medium">
                            {{ $user?->name ?: 'مدیر سیستم' }}
                        </div>

                        <div class="text-xs opacity-70">
                            {{ $user?->phone }}
                        </div>
                    </div>
                </x-button>
            </x-slot:trigger>

            <x-menu>
                <x-menu-item
                    title="بازگشت به پنل"
                    icon="lucide.layout-dashboard"
                    :link="route('panel.servers.index')"
                    wire:navigate
                />

                <x-menu-separator />

                <livewire:auth.logout />
            </x-menu>
        </x-dropdown>
    </div>
</header>
