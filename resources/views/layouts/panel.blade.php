<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="rtl"
    data-theme="light"
>

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        {{ $title ?? 'TIP' }}
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

<div
    wire:loading
    wire:target="$navigate"
    class="
            loading
            loading-bars
            loading-primary
            fixed
            top-4
            left-1/2
            -translate-x-1/2
            z-[9999]
        "
></div>

<div class="drawer lg:drawer-open">

    <input
        id="panel-drawer"
        type="checkbox"
        class="drawer-toggle"
    >

    <div
        class="
                drawer-content
                flex
                flex-col
                min-h-screen
            "
    >

        <x-panel.topbar
            :title="$title ?? null"
        />

        <main
            class="
                    flex-1
                    p-4
                    lg:p-6
                    overflow-x-hidden
                "
        >

            {{ $slot }}

        </main>

    </div>

    <div class="drawer-side">

        <label
            for="panel-drawer"
            aria-label="close sidebar"
            class="drawer-overlay"
        ></label>

        @persist('panel-sidebar')

        <x-panel.sidebar />

        @endpersist

    </div>

</div>

<x-toast position="toast-top toast-center" />

</body>

</html>
