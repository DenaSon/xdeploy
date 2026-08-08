<div
    class="relative min-w-0"
    wire:poll.visible.15s="refreshData"
>
    <div
        wire:loading.flex
        wire:target="refreshData"
        class="pointer-events-none absolute top-4 left-4 z-20
               items-center gap-1.5 rounded-full border border-base-300
               bg-base-100/85 px-2.5 py-1 text-[11px]
               text-base-content/45 shadow-sm backdrop-blur"
    >
        <span class="loading loading-spinner loading-xs"></span>
        <span>به‌روزرسانی</span>
    </div>

    @if ($errorMessage !== null)
        <x-dashboard.widget-error
            :title="$errorTitle ?? 'دریافت منابع ناموفق بود'"
            :message="$errorMessage"
            retry-action="reload"
        />
    @else
        <x-dashboard.resource-usage
            :memory="$resources['memory'] ?? []"
            :disk="$resources['disk'] ?? []"
            :load-average="$resources['loadAverage'] ?? []"
        />
    @endif
</div>
