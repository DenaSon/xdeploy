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
            ? $title . ' | ' . config('app.name')
            : config('app.name')
        }}
    </title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/easymde@2.21.0/dist/easymde.min.css"
    >

    <script src="https://unpkg.com/easymde@2.21.0/dist/easymde.min.js"></script>

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
        text-base-content
        antialiased
    "
>

<x-main full-width>

    <x-slot:sidebar
        drawer="admin-drawer"
        collapsible
        collapse-text=""
        class="border-s border-base-300 bg-base-100"
    >
        <x-admin.brand />

        <x-admin.navigation />

        <div class="mt-auto border-t border-base-300 p-2">
            <x-menu>
                <x-menu-item
                    title="بازگشت به پنل"
                    icon="lucide.arrow-right"
                    :link="route('panel.servers.index')"
                    wire:navigate
                    class="rounded-xl text-sm text-base-content/60"
                    icon-classes="!size-[18px] stroke-[1.7]"
                />
            </x-menu>
        </div>
    </x-slot:sidebar>

    <x-slot:content>
        <x-admin.header
            :title="$title ?? null"
        />

        <main
            class="
                mx-auto w-full max-w-7xl
                px-4 py-5
                sm:px-6 sm:py-6
                lg:px-8
            "
        >
            {{ $slot }}
        </main>
    </x-slot:content>

</x-main>

<x-toast
    position="toast-top toast-center"
/>

</body>
</html>
