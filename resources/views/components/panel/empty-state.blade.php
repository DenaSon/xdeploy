@props([
    'title',
    'description',
    'icon' => 'o-magnifying-glass',
])

<div
    class="
        hero
        bg-base-100
        border
        border-base-300
        rounded-box
    "
>

    <div
        class="
            hero-content
            text-center
            py-16
        "
    >

        <div>

            <x-icon
                :name="$icon"
                class="
                    w-16
                    h-16
                    mx-auto
                    text-base-content/20
                "
            />

            <h2
                class="
                    text-2xl
                    font-bold
                    mt-4
                "
            >
                {{ $title }}
            </h2>

            <p
                class="
                    text-base-content/60
                    mt-2
                "
            >
                {{ $description }}
            </p>

        </div>

    </div>

</div>
