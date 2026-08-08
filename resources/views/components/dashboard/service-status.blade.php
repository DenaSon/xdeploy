@props([
    'name',
    'status' => 'unknown',
    'description' => null,
    'subState' => null,
])

@php
    $presentation = match ($status) {
        'active' => [
            'label' => 'فعال',
            'badge' => 'badge-success',
            'dot' => 'status-success',
            'animate' => true,
        ],

        'failed' => [
            'label' => 'خطا',
            'badge' => 'badge-error',
            'dot' => 'status-error',
            'animate' => false,
        ],

        'starting' => [
            'label' => 'در حال اجرا',
            'badge' => 'badge-warning',
            'dot' => 'status-warning',
            'animate' => true,
        ],

        'stopping' => [
            'label' => 'در حال توقف',
            'badge' => 'badge-warning',
            'dot' => 'status-warning',
            'animate' => false,
        ],

        'reloading' => [
            'label' => 'در حال بارگذاری',
            'badge' => 'badge-info',
            'dot' => 'status-info',
            'animate' => true,
        ],

        'inactive' => [
            'label' => 'غیرفعال',
            'badge' => 'badge-ghost',
            'dot' => 'status-neutral',
            'animate' => false,
        ],

        default => [
            'label' => 'نامشخص',
            'badge' => 'badge-neutral',
            'dot' => 'status-neutral',
            'animate' => false,
        ],
    };
@endphp

<div
    class="rounded-2xl border border-base-content/5 bg-base-200/30
           p-3 transition-all duration-300 ease-out
           hover:border-primary/20 hover:bg-base-200/50 lg:p-4"
    @if ($description)
        title="{{ $description }}"
    @endif
>
    <div class="flex items-center justify-between gap-3">

        <div class="flex min-w-0 items-center gap-3">

            <div class="inline-grid shrink-0 *:[grid-area:1/1]">
                @if ($presentation['animate'])
                    <div
                        class="status {{ $presentation['dot'] }} animate-ping"
                    ></div>
                @endif

                <div
                    class="status {{ $presentation['dot'] }}"
                ></div>
            </div>

            <div class="min-w-0">
                <div
                    class="truncate text-sm font-medium lg:text-base"
                >
                    {{ $name }}
                </div>

                @if ($subState)
                    <div
                        dir="ltr"
                        class="mt-0.5 truncate font-mono text-[10px]
                               text-base-content/40"
                    >
                        {{ $subState }}
                    </div>
                @endif
            </div>

        </div>

        <span
            class="badge badge-sm shrink-0 {{ $presentation['badge'] }}"
        >
            {{ $presentation['label'] }}
        </span>

    </div>
</div>
