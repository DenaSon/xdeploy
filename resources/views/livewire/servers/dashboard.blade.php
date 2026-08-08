<div
    class="relative isolate mx-auto w-full max-w-[1600px]"
>
    {{-- Ambient background --}}
    <div
        class="pointer-events-none absolute -top-40 -left-40 -z-10
               size-[28rem] rounded-full bg-primary/10 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute -right-32 bottom-0 -z-10
               size-96 rounded-full bg-secondary/8 blur-3xl"
    ></div>

    <div
        class="pointer-events-none absolute top-1/3 left-1/2 -z-10
               size-80 -translate-x-1/2 rounded-full
               bg-accent/5 blur-3xl"
    ></div>

    {{--
        Lazy Dashboard shell

        The parent page contains no SSH-driven state.
        Each child widget resolves the owned Server, establishes its own
        request-scoped SSH session, validates readiness, and retrieves only
        the data required by that widget.
    --}}
    <section
        wire:key="dashboard-shell-{{ $server->getKey() }}"
        class="
            grid grid-cols-1
            gap-4 md:gap-5
            xl:grid-cols-12
            xl:items-start
            xl:gap-6
        "
    >
        {{-- Main information column --}}
        <div
            class="
                min-w-0
                space-y-4
                md:space-y-5
                xl:col-span-8
                xl:space-y-6
            "
        >
            {{-- Above the fold: load immediately after shell render --}}
            <livewire:dashboard.server-overview
                :server-id="$server->getKey()"
                defer
            />

            {{-- Below the fold: load only when visible --}}
            <livewire:dashboard.cpu-information
                :server-id="$server->getKey()"
                lazy
            />

            <livewire:dashboard.resource-usage
                :server-id="$server->getKey()"
                lazy
            />
        </div>

        {{-- Operational status column --}}
        <aside
            class="
                min-w-0
                space-y-4
                md:space-y-5
                xl:col-span-4
                xl:space-y-6
            "
        >
            {{-- Above the fold: load immediately after shell render --}}
            <livewire:dashboard.system-services
                :server-id="$server->getKey()"
                defer
            />

            {{-- Usually below services: load when it approaches viewport --}}
            <livewire:dashboard.docker-containers
                :server-id="$server->getKey()"
                lazy
            />
        </aside>
    </section>
</div>
