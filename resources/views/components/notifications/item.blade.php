@props([
    'notification',
    'compact' => false,
])

@php
    $data = is_array($notification->data)
        ? $notification->data
        : [];

    $title = (string) (
        $data['title']
        ?? 'اعلان'
    );

    $message = (string) (
        $data['message']
        ?? ''
    );

    $icon = (string) (
        $data['icon']
        ?? 'lucide.bell'
    );

    $tone = (string) (
        $data['tone']
        ?? 'neutral'
    );

    $toneClasses = match ($tone) {
        'warning' => [
            'icon' => 'bg-warning/10 text-warning',
            'dot' => 'bg-warning',
        ],

        'error' => [
            'icon' => 'bg-error/10 text-error',
            'dot' => 'bg-error',
        ],

        'success' => [
            'icon' => 'bg-success/10 text-success',
            'dot' => 'bg-success',
        ],

        'info' => [
            'icon' => 'bg-info/10 text-info',
            'dot' => 'bg-info',
        ],

        default => [
            'icon' => 'bg-base-200 text-base-content/55',
            'dot' => 'bg-primary',
        ],
    };

    $unread = $notification->read_at === null;

    $actionLabel = isset($data['action_label'])
        && is_string($data['action_label'])
            ? $data['action_label']
            : null;
@endphp

<button
    type="button"
    wire:click="openNotification('{{ $notification->id }}')"
    {{ $attributes->class([
        '
            group/notification
            flex w-full
            items-start
            text-right
            transition-all duration-150
        ',
        'gap-3 px-3.5 py-3' => $compact,
        '
            gap-3.5
            rounded-2xl
            border border-base-300/70
            bg-base-100
            px-4 py-4
            hover:-translate-y-px
            hover:border-primary/20
            hover:shadow-md
            hover:shadow-base-content/[0.025]
            sm:px-5
        ' => ! $compact,
        'bg-primary/[0.035]' => $unread && $compact,
        'bg-primary/[0.018] ring-1 ring-primary/[0.05]' => $unread && ! $compact,
        'hover:bg-base-200/60' => $compact,
    ]) }}
>
    <div
        @class([
            '
                relative
                flex shrink-0
                items-center justify-center
                rounded-xl
            ',
            'size-9' => $compact,
            'size-10' => ! $compact,
            $toneClasses['icon'],
        ])
    >
        <x-icon
            :name="$icon"
            @class([
                'stroke-[1.8]',
                '!size-4' => $compact,
                '!size-[17px]' => ! $compact,
            ])
        />

        @if($unread)
            <span
                class="
                    absolute
                    -start-0.5 -top-0.5

                    size-2
                    rounded-full

                    ring-2 ring-base-100
                    {{ $toneClasses['dot'] }}
                "
                aria-label="خوانده‌نشده"
            ></span>
        @endif
    </div>


    <div class="min-w-0 flex-1">
        @if($compact)
            <div
                class="
                    flex items-start
                    justify-between gap-3
                "
            >
                <p
                    @class([
                        'min-w-0 truncate text-sm text-base-content',
                        'font-semibold' => $unread,
                        'font-medium' => ! $unread,
                    ])
                >
                    {{ $title }}
                </p>

                <span
                    class="
                        shrink-0
                        text-[10px]
                        text-base-content/30
                    "
                >
                    {{ $notification->created_at?->diffForHumans() }}
                </span>
            </div>

            @if($message !== '')
                <p
                    class="
                        mt-1
                        line-clamp-2
                        text-xs leading-6
                        text-base-content/50
                    "
                >
                    {{ $message }}
                </p>
            @endif
        @else
            <div
                class="
                    flex min-w-0
                    items-start gap-2
                "
            >
                <p
                    @class([
                        '
                            min-w-0
                            text-sm
                            text-base-content
                            sm:text-[15px]
                        ',
                        'font-semibold' => $unread,
                        'font-medium' => ! $unread,
                    ])
                >
                    {{ $title }}
                </p>

                @if($unread)
                    <span
                        class="
                            mt-2
                            size-1.5 shrink-0
                            rounded-full
                            bg-primary
                        "
                        aria-hidden="true"
                    ></span>
                @endif
            </div>

            @if($message !== '')
                <p
                    class="
                        mt-1.5
                        max-w-3xl

                        text-xs leading-6
                        text-base-content/50

                        sm:text-[13px]
                    "
                >
                    {{ $message }}
                </p>
            @endif

            <div
                class="
                    mt-3
                    flex flex-wrap
                    items-center justify-between
                    gap-x-4 gap-y-2
                "
            >
                @if($actionLabel !== null)
                    <span
                        class="
                            inline-flex
                            items-center gap-1.5

                            text-xs
                            font-medium
                            text-primary

                            transition-colors
                            group-hover/notification:text-primary/80
                        "
                    >
                        {{ $actionLabel }}

                        <x-icon
                            name="lucide.arrow-left"
                            class="!size-3.5 stroke-[1.8]"
                        />
                    </span>
                @else
                    <span></span>
                @endif

                <span
                    class="
                        inline-flex shrink-0
                        items-center gap-1.5

                        text-[10px]
                        text-base-content/30
                    "
                >
                    <x-icon
                        name="lucide.clock-3"
                        class="!size-3 stroke-[1.6]"
                    />

                    {{ $notification->created_at?->diffForHumans() }}
                </span>
            </div>
        @endif
    </div>
</button>
