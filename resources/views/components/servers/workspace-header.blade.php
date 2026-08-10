@props([
    'server',
])

@php
    $isActive = $server->isActive();

    $statusLabel = $isActive
        ? 'آماده'
        : 'غیرفعال';

    $statusClasses = $isActive
        ? 'border-success/20 bg-success/10 text-success'
        : 'border-base-300 bg-base-200 text-base-content/60';

    $connectionAddress = $server->host
        ? $server->host . ':' . $server->port
        : '—';
@endphp

<header
    class="flex flex-col gap-5
           px-5 py-5
           sm:px-6
           lg:flex-row lg:items-center lg:justify-between"
>
    <div class="flex min-w-0 items-start gap-4">

        <div
            class="flex size-11 shrink-0 items-center justify-center
                   rounded-xl border border-base-300 bg-base-200/50"
        >
            <x-icon
                name="lucide.server"
                class="size-5 text-base-content/70"
            />
        </div>

        <div class="min-w-0">

            <div class="flex flex-wrap items-center gap-2.5">

                <h1
                    class="truncate text-xl font-semibold
                           tracking-tight text-base-content"
                >
                    {{ $server->name }}
                </h1>

                <span
                    class="inline-flex items-center gap-1.5
                           rounded-full border px-2.5 py-1
                           text-xs font-medium
                           {{ $statusClasses }}"
                >
                    <span
                        @class([
                            'size-1.5 rounded-full',
                            'bg-success' => $isActive,
                            'bg-base-content/30' => ! $isActive,
                        ])
                    ></span>

                    {{ $statusLabel }}
                </span>

            </div>

            <div
                class="mt-2 flex flex-wrap items-center
                       gap-x-4 gap-y-2
                       text-sm text-base-content/55"
            >
                <span class="inline-flex items-center gap-1.5">

                    <x-icon
                        name="lucide.network"
                        class="size-4"
                    />

                    <span
                        dir="ltr"
                        class="technical-value"
                    >
                        {{ $connectionAddress }}
                    </span>

                </span>

                <span class="inline-flex items-center gap-1.5">

                    <x-icon
                        name="lucide.user"
                        class="size-4"
                    />

                    <span
                        dir="ltr"
                        class="technical-value"
                    >
                        {{ $server->username }}
                    </span>

                </span>
            </div>

        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">

        <x-button
            label="ویرایش اتصال"
            icon="lucide.pencil"
            :link="route('panel.servers.edit', [
                'server' => $server,
            ])"
            wire:navigate
            class="btn-ghost btn-sm rounded-xl"
        />

    </div>
</header>
