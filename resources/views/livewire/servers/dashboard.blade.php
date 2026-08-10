<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    <section
        class="
            grid grid-cols-1
            gap-5
            xl:grid-cols-12
            xl:items-start
            xl:gap-6
        "
    >
        {{-- Primary server information --}}
        <div
            class="
                min-w-0
                space-y-5
                xl:col-span-8
                xl:space-y-6
            "
        >
            <livewire:dashboard.server-overview
                :server-id="$server->getKey()"
                defer
            />

            <livewire:dashboard.cpu-information
                :server-id="$server->getKey()"
                lazy
            />

            <livewire:dashboard.resource-usage
                :server-id="$server->getKey()"
                lazy
            />
        </div>

        {{-- Operational status --}}
        <aside
            class="
                min-w-0
                space-y-5
                xl:col-span-4
                xl:space-y-6
            "
        >
            <livewire:dashboard.system-services
                :server-id="$server->getKey()"
                defer
            />

            <livewire:dashboard.docker-containers
                :server-id="$server->getKey()"
                lazy
            />
        </aside>
    </section>
</x-servers.workspace>
