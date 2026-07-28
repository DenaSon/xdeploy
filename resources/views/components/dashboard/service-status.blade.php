@props([
    'name',
    'active' => false,
])

<div
    class="
        rounded-2xl

        border border-base-content/5
        bg-base-200/30

        p-3 lg:p-4

        transition-all duration-300 ease-out

        hover:bg-base-200/50
        hover:border-primary/20
    "
>

    <div class="flex items-center justify-between gap-3">

        <div class="flex min-w-0 items-center gap-3">

            <div class="inline-grid shrink-0 *:[grid-area:1/1]">

                @if($active)
                    <div class="status status-success animate-ping"></div>
                    <div class="status status-success"></div>
                @else
                    <div class="status status-error"></div>
                @endif

            </div>

            <span class="truncate text-sm font-medium lg:text-base">
                {{ $name }}
            </span>

        </div>

        <span
            @class([
                'badge badge-sm shrink-0',
                'badge-success' => $active,
                'badge-error' => ! $active,
            ])
        >
            {{ $active ? 'فعال' : 'غیرفعال' }}
        </span>

    </div>

</div>
