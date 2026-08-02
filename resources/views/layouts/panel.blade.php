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

<x-main full-width>

    <x-slot:sidebar
        drawer="panel-drawer"
        collapsible
        collapse-text=""
        class="bg-base-300"
    >

        <x-panel.brand />

        <x-panel.navigation />

    </x-slot:sidebar>

    <x-slot:content>

        <x-panel.header/>

        <x-panel.page-container>
            {{ $slot }}
        </x-panel.page-container>

    </x-slot:content>

    <x-slot:footer>

        <x-panel.footer />

    </x-slot:footer>

</x-main>

{{-- Global Toast --}}
<x-toast position="toast-top toast-center" />

</body>
</html>
