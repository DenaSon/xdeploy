<aside
    class="
        bg-base-100
        min-h-full
        w-64
        border-l
        border-base-300
        flex
        flex-col
    "
>

    {{-- Brand --}}
    <div
        class="
            px-5
            py-5
            border-b
            border-base-300
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
                    size-11
                    rounded-2xl
                    bg-primary
                    text-primary-content
                    flex
                    items-center
                    justify-center
                    shadow-sm
                    shrink-0
                "
            >

                <x-icon
                    name="o-bolt"
                    class="w-5 h-5"
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
                    TIP
                </h2>

                <p
                    class="
                        hidden
                        lg:block
                        text-xs
                        text-base-content/60
                        mt-1
                    "
                >
                    Trend Intelligence Platform
                </p>

            </div>

        </div>

    </div>

    {{-- Navigation --}}
    <div class="flex-1 py-3">

        <ul
            class="
                menu
                w-full
                px-3
                gap-1
            "
        >

            <li>

                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('panel.opportunities.index') }}"
                >

                    <x-icon
                        name="o-light-bulb"
                        class="w-5 h-5"
                    />

                    <span>
                        فرصت‌ها
                    </span>

                </a>

            </li>

            <li>

                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('panel.trends.index') }}"
                >

                    <x-icon
                        name="o-fire"
                        class="w-5 h-5"
                    />

                    <span>
                        روندها
                    </span>

                </a>

            </li>

        </ul>

    </div>



</aside>
