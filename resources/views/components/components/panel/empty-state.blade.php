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
        "
    >

        <div>

            <x-icon
                :name="$icon"
                class="
                    w-16
                    h-16
                    mx-auto
                    text-base-content/30
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
                    mt-2
                    text-base-content/60
                "
            >
                {{ $description }}
            </p>

        </div>

    </div>

</div>
