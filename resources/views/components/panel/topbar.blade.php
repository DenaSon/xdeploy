@props([
    'breadcrumbs' => [],
])

<header
    class="
        sticky
        top-0
        z-40
        border-b
        border-base-300
        bg-base-100/90
        backdrop-blur
    "
>

    <div
        class="
            mx-auto
            max-w-6xl
        "
    >

        <div
            class="
                navbar
                min-h-16
                px-4
                lg:px-6
            "
        >

            {{-- Start --}}
            <div class="navbar-start">

                <label
                    for="panel-drawer"
                    class="
                        btn
                        btn-ghost
                        btn-square
                        lg:hidden
                    "
                >

                    <x-icon
                        name="o-bars-3"
                        class="size-6"
                    />

                </label>

                @if (count($breadcrumbs))

                    <div
                        class="
                            hidden
                            lg:flex
                            items-center
                            mr-2
                        "
                    >

                        <x-breadcrumbs
                            :items="$breadcrumbs"
                        />

                    </div>

                @endif

            </div>

            {{-- End --}}
            <div
                class="
                    navbar-end
                    gap-2
                "
            >

                {{-- Future:
                    - Server Status
                    - Notifications
                    - User Menu
                --}}

                <label
                    class="
                        swap
                        swap-rotate
                        btn
                        btn-ghost
                        btn-circle
                    "
                >

                    <input
                        id="theme-toggle"
                        type="checkbox"
                        class="theme-controller"
                        value="dark"
                    />

                    <x-icon
                        name="o-sun"
                        class="
                            swap-off
                            size-5
                        "
                    />

                    <x-icon
                        name="o-moon"
                        class="
                            swap-on
                            size-5
                        "
                    />

                </label>

            </div>

        </div>

    </div>

</header>
