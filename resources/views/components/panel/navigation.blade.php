@php
    use App\Application\Navigation\PanelNavigation;
@endphp

<nav class="flex-1 py-4">

    <x-menu class="">

        @foreach (PanelNavigation::items() as $item)

            <x-menu-item
                :title="$item['title']"
                :icon="$item['icon']"
                :link="$item['route']"
                :active="request()->routeIs($item['name'])"
                wire:navigate
                class="rounded-xl"
            />

        @endforeach

    </x-menu>

</nav>
