<!DOCTYPE html>

<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="rtl"
>
<head>
    <meta name="enamad" content="33187641" />
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
            ? $title . ' | ' . config('app.name')
            : config('app.name')
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

<div class="flex min-h-dvh flex-col">

    {{-- Public Header --}}
    <x-public.header />


    {{-- Page Content --}}
    <main class="min-w-0 flex-1">
        {{ $slot }}
    </main>


    {{-- Public Footer --}}
    <x-public.footer />

</div>


{{-- Global Toast --}}
<x-global-toast />


@livewireScripts

</body>
</html>
