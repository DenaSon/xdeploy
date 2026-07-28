@php
    $user = auth()->user();
@endphp

<x-dropdown>
    <x-slot:trigger>

        <x-button
            icon="lucide.user"
            class="btn-ghost"
            responsive
        >

            <div class="hidden text-right lg:block">

                <div class="font-medium">
                    {{ $user?->name }}
                </div>

                <div class="text-xs opacity-70">
                    {{ $user?->mobile ?? $user?->email }}
                </div>

            </div>

        </x-button>

    </x-slot:trigger>

    <x-menu>

        <x-menu-item
            title="پروفایل"
            icon="lucide.user"
            link=""
        />

        <x-menu-separator />

      <livewire:auth.logout/>

    </x-menu>

</x-dropdown>
