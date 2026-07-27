<footer
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
        {{ config('app.name') }}
    </div>

    <div
        class="
            mt-1
            text-sm
            font-medium
        "
    >
        v{{ config('app.version', '0.1.0-alpha') }}
    </div>

</footer>
