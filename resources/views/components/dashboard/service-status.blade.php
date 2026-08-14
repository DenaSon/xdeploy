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
            'badge' => 'border-success/20 bg-success/10 text-success',
            'dot' => 'bg-success',
            'animate' => false,
        ],

        'failed' => [
            'label' => 'خطا',
            'badge' => 'border-error/20 bg-error/10 text-error',
            'dot' => 'bg-error',
            'animate' => false,
        ],

        'starting' => [
            'label' => 'در حال اجرا',
            'badge' => 'border-warning/20 bg-warning/10 text-warning',
            'dot' => 'bg-warning',
            'animate' => true,
        ],

        'stopping' => [
            'label' => 'در حال توقف',
            'badge' => 'border-warning/20 bg-warning/10 text-warning',
            'dot' => 'bg-warning',
            'animate' => true,
        ],

        'reloading' => [
            'label' => 'در حال بارگذاری',
            'badge' => 'border-info/20 bg-info/10 text-info',
            'dot' => 'bg-info',
            'animate' => true,
        ],

        'inactive' => [
            'label' => 'غیرفعال',
            'badge' => 'border-base-300 bg-base-200 text-base-content/55',
            'dot' => 'bg-base-content/30',
            'animate' => false,
        ],

        default => [
            'label' => 'نامشخص',
            'badge' => 'border-base-300 bg-base-200 text-base-content/55',
            'dot' => 'bg-base-content/30',
            'animate' => false,
        ],
    };
@endphp


<div
    @if($description)
        title="{{ $description }}"
    @endif
    class="
        flex min-w-0
        items-center justify-between
        gap-3

        rounded-xl

        border border-base-300/80
        bg-base-100

        px-3 py-2.5
    "
>
    <div
        class="
            flex min-w-0
            items-center gap-2.5
        "
    >
        {{-- Status indicator --}}
        <div
            class="
                relative

                flex size-7 shrink-0
                items-center justify-center

                rounded-lg
                bg-base-200/60
            "
        >
            @if($presentation['animate'])
                <span
                    class="
                        absolute

                        size-2.5
                        rounded-full

                        {{ $presentation['dot'] }}

                        animate-ping
                        opacity-30
                    "
                ></span>
            @endif

            <span
                class="
                    relative

                    size-2
                    rounded-full

                    {{ $presentation['dot'] }}
                "
            ></span>
        </div>


        {{-- Service information --}}
        <div class="min-w-0">
            <p
                dir="ltr"
                class="
                    technical-value

                    truncate
                    text-right

                    text-xs
                    font-medium
                    text-base-content

                    sm:text-sm
                "
            >
                {{ $name }}
            </p>


            @if($subState)
                <p
                    dir="ltr"
                    class="
                        technical-value

                        mt-0.5
                        truncate
                        text-right

                        text-[10px]
                        text-base-content/35
                    "
                >
                    {{ $subState }}
                </p>

            @elseif($description)
                <p
                    class="
                        mt-0.5
                        truncate

                        text-[10px]
                        text-base-content/35
                    "
                >
                    {{ $description }}
                </p>
            @endif
        </div>
    </div>


    {{-- Status --}}
    <span
        class="
            inline-flex
            shrink-0
            items-center

            rounded-full

            border

            px-2 py-0.5

            text-[10px]
            font-medium

            {{ $presentation['badge'] }}
        "
    >
        {{ $presentation['label'] }}
    </span>
</div>
