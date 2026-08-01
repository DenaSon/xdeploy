@php
    $headers = [
        ['key' => 'name', 'label' => 'سرور'],
        ['key' => 'host', 'label' => 'آدرس اتصال'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-48'],
    ];
@endphp

<div dir="rtl" class="space-y-7">

    {{-- Page header --}}
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex items-center gap-4">

            <div
                class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/10"
            >
                <x-icon
                    name="o-server-stack"
                    class="size-6"
                />
            </div>

            <div>
                <div class="flex items-center gap-3">

                    <h1 class="text-2xl font-bold tracking-tight text-base-content">
                        سرورها
                    </h1>

                    @if($servers->isNotEmpty())
                        <span class="badge badge-primary badge-soft">
                            {{ $servers->count() }}
                        </span>
                    @endif

                </div>

                <p class="mt-1 text-sm leading-6 text-base-content/55">
                    سرورهای VPS خود را اضافه و مدیریت کنید.
                </p>
            </div>

        </div>

        <x-button
            label="افزودن سرور"
            icon="o-plus"
            :link="route('panel.servers.create')"
            class="btn-primary shadow-lg shadow-primary/15 transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/20"
        />

    </div>

    {{-- Server list --}}
    <div
        class="overflow-hidden rounded-2xl border border-base-300/70 bg-base-100 shadow-xl shadow-base-300/20"
    >
        {{-- Card header --}}
        <div class="flex items-center justify-between border-b border-base-300/70 px-5 py-4 sm:px-6">

            <div>
                <h2 class="font-semibold text-base-content">
                    لیست سرورها
                </h2>

                <p class="mt-1 text-xs text-base-content/45">
                    مدیریت اتصال و تنظیمات سرورهای شما
                </p>
            </div>

            <div
                class="flex size-9 items-center justify-center rounded-xl bg-base-200 text-base-content/45"
            >
                <x-icon
                    name="o-circle-stack"
                    class="size-5"
                />
            </div>

        </div>

        <x-table
            :headers="$headers"
            :rows="$servers"
            class="w-full"
        >

            {{-- Server --}}
            @scope('cell_name', $server)

            <div class="flex items-center gap-3 py-2">

                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10"
                >
                    <x-icon
                        name="o-server"
                        class="size-5"
                    />
                </div>

                <div class="min-w-0">

                    <div class="truncate font-semibold text-base-content">
                        {{ $server->name }}
                    </div>

                    <div class="mt-0.5 text-xs text-base-content/40">
                        VPS Server
                    </div>

                </div>

            </div>

            @endscope

            {{-- Host --}}
            @scope('cell_host', $server)

            <div
                dir="ltr"
                class="inline-flex items-center gap-2 rounded-lg bg-base-200 px-3 py-2 font-mono text-xs font-medium text-base-content/70"
            >
                <span class="size-1.5 rounded-full bg-success"></span>

                <span>{{ $server->host }}:{{ $server->port }}</span>
            </div>

            @endscope

            {{-- Actions --}}
            @scope('cell_actions', $server)

            <div class="flex items-center justify-end gap-2">

                <x-button
                    label="مدیریت"
                    icon="o-cog-6-tooth"
                    :link="route('panel.servers.dashboard', $server)"
                    class="btn-primary btn-sm"
                />

                <x-button
                    icon="o-trash"
                    wire:click="delete({{ $server->getKey() }})"
                    wire:confirm="آیا از حذف این سرور مطمئن هستید؟"
                    class="btn-ghost btn-sm text-error hover:bg-error/10"
                    tooltip="حذف سرور"
                />

            </div>

            @endscope

            {{-- Empty state --}}
            <x-slot:empty>

                <div class="px-6 py-16 text-center sm:py-20">

                    <div
                        class="relative mx-auto flex size-20 items-center justify-center rounded-3xl bg-base-200"
                    >
                        <div
                            class="absolute inset-0 rounded-3xl bg-primary/5 blur-xl"
                        ></div>

                        <x-icon
                            name="o-server-stack"
                            class="relative size-10 text-base-content/25"
                        />
                    </div>

                    <h3 class="mt-6 text-lg font-bold text-base-content">
                        هنوز سروری اضافه نکرده‌اید
                    </h3>

                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-base-content/50">
                        اولین سرور VPS خود را اضافه کنید تا بتوانید وضعیت و سرویس‌های آن را از طریق xDeploy مدیریت کنید.
                    </p>

                    <x-button
                        label="افزودن اولین سرور"
                        icon="o-plus"
                        :link="route('panel.servers.create')"
                        class="btn-primary mt-7 shadow-lg shadow-primary/15"
                    />

                </div>

            </x-slot:empty>

        </x-table>

    </div>

</div>
