@props([
    'title' => null,
    'description' => null,
])

@if($title || $description)

    <div class="mb-8">

        @if($title)

            <h1
                class="
                    text-3xl
                    font-black
                    tracking-tight
                "
            >
                {{ $title }}
            </h1>

        @endif

        @if($description)

            <p
                class="
                    text-base-content/70
                    mt-2
                    leading-7
                "
            >
                {{ $description }}
            </p>

        @endif

    </div>

@endif
