@props([
    'server',
])

<div
    {{ $attributes->class([
        'min-w-0 space-y-4 sm:space-y-5',
    ]) }}
>
    {{-- Server workspace navigation --}}
    <x-servers.workspace-header
        :server="$server"
    />


    {{-- Page content --}}
    <main class="min-w-0">
        {{ $slot }}
    </main>
</div>
