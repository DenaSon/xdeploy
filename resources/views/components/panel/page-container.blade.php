<main class="flex-1 p-4 lg:p-6">

    <div
        class="
            relative overflow-hidden

            rounded-3xl lg:rounded-[2rem]

            border border-base-content/5
            bg-base-100/55

            backdrop-blur-md

            shadow-[0_20px_60px_rgba(15,23,42,0.05)]

            transition-colors duration-300
        "
    >

        {{-- Top Highlight --}}
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/30"
        ></div>

        <div class="p-4 lg:p-6">

            {{ $slot }}

        </div>

    </div>

</main>
