<div
    wire:offline
    role="alert"
    class="
        fixed inset-x-3 bottom-4 z-[100]
        mx-auto max-w-md

        rounded-2xl
        border border-warning/20
        bg-base-100/90
        p-3

        shadow-xl shadow-base-content/10
        backdrop-blur-xl
    "
>
    <div class="flex items-center gap-3">

        <span
            class="
                flex size-9 shrink-0
                items-center justify-center
                rounded-xl
                bg-warning/10
                text-warning
            "
        >
            <x-icon
                name="lucide.wifi-off"
                class="!size-4 stroke-[1.8]"
            />
        </span>

        <div class="min-w-0">
            <p class="text-sm font-semibold">
                اتصال اینترنت قطع شده است
            </p>

            <p class="mt-0.5 text-xs text-base-content/50">
                تا زمان برقراری اتصال، عملیات جدید اجرا نمی‌شود.
            </p>
        </div>

        <span class="ms-auto inline-grid shrink-0 *:[grid-area:1/1]">
            <span class="status status-warning animate-ping"></span>
            <span class="status status-warning"></span>
        </span>

    </div>
</div>
