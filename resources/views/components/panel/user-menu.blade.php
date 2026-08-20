@php
    use App\Models\User;

    $user = auth()->user();
@endphp

<x-dropdown>
    <x-slot:trigger>

        <x-button
            icon="lucide.user"
            aria-label="منوی حساب کاربری"
            class="
                btn-ghost
                max-sm:h-10 max-sm:min-h-10
                max-sm:min-w-10 max-sm:rounded-xl
                max-sm:px-2
            "
            responsive
        >

            <div class="hidden text-right lg:block">

                <div class="font-medium">
                    {{ $user instanceof User
                        ? ($user->displayName() ?? 'حساب کاربری')
                        : ''
                    }}
                </div>

                <div class="text-xs opacity-70">
                    {{ $user?->phone }}
                </div>

            </div>

        </x-button>

    </x-slot:trigger>

    <x-menu>

        @if ($user instanceof User && $user->isAdmin())
            <x-menu-item
                title="مدیریت سیستم"
                icon="lucide.shield-check"
                :link="route('admin.dashboard')"
                wire:navigate
            />

            <x-menu-separator />
        @endif

        <x-menu-item
            title="پروفایل"
            icon="lucide.user"
            :link="route('panel.profile')"
            wire:navigate
        />

        <x-menu-separator />

        <livewire:auth.logout />

    </x-menu>

</x-dropdown>
