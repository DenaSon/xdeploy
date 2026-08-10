<!DOCTYPE html>

<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="rtl"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="color-scheme"
        content="light dark"
    >

    <title>
        {{ isset($title) && $title
            ? $title . ' | xDeploy'
            : 'xDeploy'
        }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    @livewireStyles
</head>

<body
    class="
        min-h-screen
        bg-base-100
        font-sans
        text-base-content
        antialiased
    "
>

<div class="flex min-h-screen flex-col">

    {{-- Public Header --}}
    <x-public.header />


    {{-- Page Content --}}
    <main class="flex-1">
        {{ $slot }}
    </main>


    {{-- Public Footer --}}
    <x-public.footer />

</div>


{{-- Global Toast --}}
<x-toast position="toast-top toast-center" />


@livewireScripts

</body>
</html>
