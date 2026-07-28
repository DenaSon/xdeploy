<div class="space-y-6">

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">

        {{-- Server Status --}}
        <div class="xl:col-span-8">
            <livewire:panel.server-overview />
        </div>

        {{-- Services --}}
        <div class="xl:col-span-4">
            <livewire:panel.service-status />
        </div>

        {{-- CPU --}}
        <div class="xl:col-span-12">
            <livewire:panel.cpu-info />
        </div>

        {{-- Resources --}}
        <div class="xl:col-span-12">
            <livewire:panel.resource-usage />
        </div>

    </section>

</div>
