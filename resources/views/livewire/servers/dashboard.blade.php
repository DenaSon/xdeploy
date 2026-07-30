<div class="relative mx-auto max-w-full space-y-6 overflow-hidden">

    <div class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full bg-primary/8 blur-3xl"></div>

    <div class="pointer-events-none absolute right-0 bottom-0 h-80 w-80 rounded-full bg-secondary/6 blur-3xl"></div>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">

        {{-- Server Overview --}}
        <div class="xl:col-span-8">
            <livewire:servers.server-overview  :server="$server" />
        </div>

        {{-- Services --}}
        <div class="xl:col-span-4">
            <livewire:servers.service-status  :server="$server" />
        </div>

        {{-- CPU --}}
        <div class="xl:col-span-12">
            <livewire:servers.cpu-info  :server="$server" />
        </div>

        {{-- Resources --}}
        <div class="xl:col-span-12">
            <livewire:servers.resource-usage  :server="$server" />
        </div>

    </section>

</div>
