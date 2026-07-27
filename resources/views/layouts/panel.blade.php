<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="rtl"
    data-theme="{{ config('app.theme', 'light') }}"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        {{ $title ?? __('Dashboard') }} | xDeploy
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body
    class="
        min-h-screen
        bg-base-200
        font-sans
    "
>

{{-- Global Navigation Loading --}}
<x-panel.loading-indicator />

{{-- Main Drawer Layout --}}
<x-panel.drawer>

    {{-- Header --}}
    <x-panel.header
        :breadcrumbs="$breadcrumbs ?? []"
    />

    {{-- Page Content --}}
    <x-panel.page-container>

        {{ $slot }}

    </x-panel.page-container>

</x-panel.drawer>

{{-- Global Toast --}}
<x-toast position="toast-top toast-center" />

</body>
</html>
