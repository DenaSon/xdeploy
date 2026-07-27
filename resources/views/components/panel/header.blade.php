<header
    class="
        navbar
        sticky
        top-0
        z-30
        border-b
        border-base-300
        bg-base-100/90
        px-4
        backdrop-blur
        lg:px-6
    "
>

    {{-- Left Section --}}
    <div class="navbar-start gap-2">

        {{-- Mobile Drawer Toggle --}}
        <label
            for="panel-drawer"
            class="btn btn-square btn-ghost lg:hidden"
        >
            <x-icon
                name="lucide.menu"
                class="h-5 w-5"
            />
        </label>

        {{-- Breadcrumb --}}
        <x-panel.breadcrumb
            :items="$breadcrumbs"
        />

    </div>

    {{-- Center Section --}}
    <div class="navbar-center hidden lg:flex">

        @isset($title)

            <h1 class="text-lg font-semibold">

                {{ $title }}

            </h1>

        @endisset

    </div>

    {{-- Right Section --}}
    <div class="navbar-end gap-2">

        {{-- Theme Switcher (Sprint Later) --}}
        {{-- <x-panel.theme-switcher /> --}}

        {{-- Notifications (Sprint Later) --}}
        {{-- <x-panel.notifications /> --}}

        {{-- User Menu --}}
        <x-panel.user-menu />

    </div>

</header>
