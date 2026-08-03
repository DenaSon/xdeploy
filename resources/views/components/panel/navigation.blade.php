@php
    use App\Application\Navigation\PanelNavigation;
@endphp

<nav class="flex-1 py-4">

    <x-menu class="space-y-1">

        @foreach (PanelNavigation::items() as $item)

            @php
                $isActive = request()->routeIs($item['name']);
            @endphp

            <x-menu-item
                :title="$item['title']"
                :icon="$item['icon']"
                :link="$item['route']"
                :active="$isActive"
                :icon-classes="$isActive
                    ? '!size-4 text-primary stroke-[1.8]'
                    : '!size-4 text-primary/65 stroke-[1.6]'"
                wire:navigate
                @class([
                    '
                        rounded-xl
                        border border-transparent

                        text-sm
                        text-base-content/60

                        transition-all duration-200

                        hover:bg-primary/5
                        hover:text-base-content
                    ',

                    '
                        !border-primary/10
                        !bg-primary/10
                        !font-medium
                        !text-primary
                    ' => $isActive,
                ])
            />

        @endforeach

    </x-menu>

</nav>
