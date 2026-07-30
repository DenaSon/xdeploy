@php
    $headers = [
        ['key' => 'name', 'label' => 'سرور'],
        ['key' => 'host', 'label' => 'آدرس'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-48'],
    ];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold">
                سرورها
            </h1>

            <p class="text-base-content/60">
                سرورهای VPS خود را مدیریت کنید.
            </p>
        </div>

        <x-button
            label="افزودن سرور"
            icon="o-plus"
            :link="route('panel.servers.create')"
            class="btn-primary"
        />

    </div>

    {{-- Table --}}
    <x-card>

        <x-table
            :headers="$headers"
            :rows="$servers"
            striped
        >

            {{-- Server --}}
            @scope('cell_name', $server)

            <div class="font-semibold">
                {{ $server->name }}
            </div>

            @endscope

            {{-- Host --}}
            @scope('cell_host', $server)

            <span class="font-mono text-sm">
                    {{ $server->host }}:{{ $server->port }}
                </span>

            @endscope

            {{-- Actions --}}
            @scope('cell_actions', $server)

            <div class="flex justify-end gap-2">

                <x-button
                    label="مدیریت"
                    icon="o-cog-6-tooth"
                    :link="route('panel.servers.dashboard', $server)"
                    class="btn-primary btn-sm"
                />

                <x-button
                    icon="o-trash"
                    wire:click="delete({{ $server->id }})"
                    wire:confirm="آیا از حذف این سرور مطمئن هستید؟"
                    class="btn-ghost btn-sm text-error"
                    tooltip="حذف"
                />

            </div>

            @endscope

            {{-- Empty State --}}
            <x-slot:empty>

                <div class="flex flex-col items-center py-16">

                    <x-icon
                        name="o-server-stack"
                        class="size-14 text-base-content/20"
                    />

                    <h3 class="mt-4 text-lg font-semibold">
                        هنوز سروری اضافه نکرده‌اید.
                    </h3>

                    <p class="mt-2 text-center text-base-content/60">
                        برای شروع، اولین سرور خود را به xDeploy اضافه کنید.
                    </p>

                    <x-button
                        label="افزودن سرور"
                        icon="o-plus"
                        :link="route('panel.servers.create')"
                        class="btn-primary mt-6"
                    />

                </div>

            </x-slot:empty>

        </x-table>

    </x-card>

</div>
