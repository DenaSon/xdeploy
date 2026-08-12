@php
    use App\Models\Server;

    $routeServer = request()->route('server');

    $contextServer = $routeServer instanceof Server
        ? $routeServer
        : null;
@endphp

<nav class="flex-1 px-2 py-4">

    <x-menu>

        <x-menu-item
            title="سرورها"
            icon="lucide.server"
            :link="route('panel.servers.index')"
            :active="request()->routeIs('panel.servers.*')
                && ! request()->routeIs('panel.servers.domains.*')"
            wire:navigate

            class="
                rounded-xl

                text-sm
                text-base-content/65

                transition-colors duration-200

                hover:bg-base-200
                hover:text-base-content
            "

            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "

            icon-classes="
                !size-[18px]
                stroke-[1.7]
            "
        />

        @if ($contextServer !== null)
            <x-menu-item
                title="دامنه‌ها"
                icon="lucide.globe-2"
                :link="route(
                    'panel.servers.domains.index',
                    ['server' => $contextServer],
                )"
                :active="request()->routeIs('panel.servers.domains.*')"
                wire:navigate

                class="
                    rounded-xl

                    text-sm
                    text-base-content/65

                    transition-colors duration-200

                    hover:bg-base-200
                    hover:text-base-content
                "

                active-bg-color="
                    !bg-primary/10
                    !text-primary
                    !font-medium
                "

                icon-classes="
                    !size-[18px]
                    stroke-[1.7]
                "
            />
        @endif

    </x-menu>

</nav>
