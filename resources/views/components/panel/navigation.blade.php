@php
    use App\Application\Navigation\PanelNavigation;
@endphp

<nav class="flex-1 py-4">

    <div class="px-5 pb-2 text-xs font-semibold uppercase tracking-wider text-base-content/50">
        {{ __('panel.navigation') }}
    </div>

    <ul class="menu w-full gap-1 px-3">

        @foreach(PanelNavigation::items() as $item)

            <li>

                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ $item['route'] }}"
                >

                    <x-icon
                        :name="$item['icon']"
                        class="size-5"
                    />

                    {{ $item['title'] }}

                </a>

            </li>

        @endforeach

    </ul>

</nav>
