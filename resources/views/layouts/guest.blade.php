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
        bg-base-200
        font-sans
        text-base-content
        antialiased
    "
>

<div class="flex min-h-screen flex-col">

    {{-- Public Header --}}
    <x-public.header />


    {{-- Main --}}
    <main
        class="
            relative
            flex flex-1
            items-center justify-center
            overflow-hidden

            px-4 py-10
            sm:px-6 sm:py-12
            lg:px-8
        "
    >

        {{-- Subtle brand atmosphere --}}
        <div
            aria-hidden="true"
            class="
                pointer-events-none
                absolute inset-0
                overflow-hidden
            "
        >
            <div
                class="
                    absolute
                    start-1/2 top-[-12rem]

                    size-[34rem]
                    -translate-x-1/2

                    rounded-full
                    bg-primary/[0.06]
                    blur-3xl
                "
            ></div>

            <div
                class="
                    absolute
                    bottom-[-16rem] end-[-10rem]

                    size-[28rem]

                    rounded-full
                    bg-primary/[0.035]
                    blur-3xl
                "
            ></div>
        </div>


        {{-- Auth Content --}}
        <div
            class="
                relative z-10
                w-full max-w-md
            "
        >
            {{ $slot }}
        </div>

    </main>


    {{-- Footer --}}
    <footer
        class="
            relative z-10

            px-4 py-5

            text-center
            text-[11px]
            text-base-content/40
        "
    >
        <span dir="ltr">
            xDeploy by Lumixo
        </span>
    </footer>

</div>


{{-- Global Toast --}}
<x-toast position="toast-top toast-center" />


@livewireScripts

</body>
</html>
