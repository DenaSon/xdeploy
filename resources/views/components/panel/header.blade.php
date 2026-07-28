@props([
    'breadcrumbs' => [],
    'title' => null,
])

<header
    class="
        navbar
        sticky
        top-0
        z-20

        h-14
        min-h-14

        border-b
        border-base-300/70

        bg-base-100/85
        backdrop-blur-xl

        px-3
        lg:px-5
    "
>

    <div class="navbar-start min-w-0 gap-2">

        <label
            for="panel-drawer"
            class="btn btn-square btn-ghost btn-sm lg:hidden"
        >
            <x-icon
                name="lucide.menu"
                class="size-5"
            />
        </label>

        @if($breadcrumbs)

            <div class="breadcrumbs text-sm">

                <ul>

                    @foreach($breadcrumbs as $breadcrumb)

                        <li>

                            @if(!empty($breadcrumb['url']))

                                <a
                                    href="{{ $breadcrumb['url'] }}"
                                    wire:navigate
                                    class="text-base-content/60 hover:text-base-content transition-colors"
                                >
                                    {{ $breadcrumb['label'] }}
                                </a>

                            @else

                                <span class="font-medium text-base-content">
                                    {{ $breadcrumb['label'] }}
                                </span>

                            @endif

                        </li>

                    @endforeach

                </ul>

            </div>

        @endif

    </div>

    <div class="navbar-end gap-1">

        <x-theme-toggle />

        <x-panel.user-menu />

    </div>

</header>
