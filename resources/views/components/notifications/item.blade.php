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
@endphp

<button
    type="button"
    wire:click="openNotification('{{ $notification->id }}')"
    {{ $attributes->class([
        '
            group/notification
            flex w-full
            items-start gap-3
            text-right
            transition-colors duration-150
        ',
        'px-3.5 py-3' => $compact,
        'rounded-xl border border-base-300 bg-base-100 px-4 py-4' => ! $compact,
        'bg-primary/[0.035]' => $unread && $compact,
        'hover:bg-base-200/60',
    ]) }}
>
    <div
        class="
            relative
            flex size-9 shrink-0
            items-center justify-center
            rounded-xl
            {{ $toneClasses['icon'] }}
        "
    >
        <x-icon
            :name="$icon"
            class="!size-4 stroke-[1.8]"
        />

        @if($unread)
            <span
                class="
                    absolute -start-0.5 -top-0.5
                    size-2 rounded-full
                    ring-2 ring-base-100
                    {{ $toneClasses['dot'] }}
                "
                aria-label="خوانده‌نشده"
            ></span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
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
                @class([
                    '
                        mt-1
                        text-xs leading-6
                        text-base-content/50
                    ',
                    'line-clamp-2' => $compact,
                ])
            >
                {{ $message }}
            </p>
        @endif

        @if(
            ! $compact
            && isset($data['action_label'])
            && is_string($data['action_label'])
        )
            <div
                class="
                    mt-2
                    inline-flex items-center gap-1
                    text-xs font-medium
                    text-primary
                "
            >
                <span>
                    {{ $data['action_label'] }}
                </span>

                <x-icon
                    name="lucide.arrow-left"
                    class="!size-3.5"
                />
            </div>
        @endif
    </div>
</button>
