@php
    $menu = [
        [
            'title' => __('panel.dashboard'),
            'icon' => 'o-home',
            'route' => route('panel.panel'),
        ],

        [
            'title' => __('panel.modules'),
            'icon' => 'o-squares-2x2',
             'route' => route('panel.panel'),
        ],

        [
            'title' => __('panel.settings'),
            'icon' => 'o-cog-6-tooth',
             'route' => route('panel.panel'),
        ],
    ];
@endphp

<aside
    class="
        flex
        min-h-full
        w-64
        flex-col
        border-l
        border-base-300
        bg-base-100
    "
>

    {{-- Brand --}}
    <div
        class="
            border-b
            border-base-300
            px-5
            py-5
        "
    >

        <div
            class="
                flex
                items-center
                gap-3
            "
        >

            <div
                class="
                    flex
                    size-11
                    shrink-0
                    items-center
                    justify-center
                    rounded-2xl
                    bg-primary
                    text-primary-content
                    shadow-sm
                "
            >

                <x-icon
                    name="o-rocket-launch"
                    class="size-5"
                />

            </div>

            <div>

                <h2
                    class="
                        text-lg
                        font-black
                        leading-none
                    "
                >
                    xDeploy
                </h2>

                <p
                    class="
                        mt-1
                        text-xs
                        text-base-content/60
                    "
                >
                    Deploy & Manage VPS
                </p>

            </div>

        </div>

    </div>

    {{-- Navigation --}}
    <div
        class="
            flex-1
            py-4
        "
    >

        <div
            class="
                px-5
                pb-2
                text-xs
                font-semibold
                uppercase
                tracking-wider
                text-base-content/50
            "
        >
            Main
        </div>

        <ul
            class="
                menu
                w-full
                px-3
                gap-1
            "
        >

            @foreach($menu as $item)

                <li>

                    <a
                        wire:navigate
                        wire:current="menu-active"
                        href="{{ $item['route'] }}"
                    >

                        <x-icon
                            :name="$item['icon']"
                            class="size-5"
                        />

                        <span>
                            {{ $item['title'] }}
                        </span>

                    </a>

                </li>

            @endforeach

        </ul>

    </div>

    {{-- Footer --}}
    <div
        class="
            border-t
            border-base-300
            px-5
            py-4
        "
    >

        <div
            class="
                text-xs
                text-base-content/50
            "
        >
            xDeploy
        </div>

        <div
            class="
                mt-1
                font-medium
                text-sm
            "
        >
            v0.1.0-alpha
        </div>

    </div>

</aside>
