@props([
    'server',
])

<div
    {{ $attributes->class([
        'space-y-5',
    ]) }}
>
    <section
        class="overflow-hidden rounded-2xl
               border border-base-300
               bg-base-100"
    >
        <x-servers.workspace-header
            :server="$server"
        />
    </section>

    <div>
        {{ $slot }}
    </div>
</div>
