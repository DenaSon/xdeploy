@props([
    'message',
    'adminView' => false,
])

@php
    $fromAdmin = $message->isFromAdmin();
    $ownSide = $adminView
        ? $fromAdmin
        : ! $fromAdmin;

    $authorLabel = $fromAdmin
        ? 'پشتیبانی '.config('app.name')
        : (
            $adminView
                ? ($message->author?->displayName() ?? $message->author?->phone ?? 'کاربر')
                : 'شما'
        );
@endphp

<div
    @class([
        'flex',
        'justify-start' => $ownSide,
        'justify-end' => ! $ownSide,
    ])
>
    <article
        @class([
            'max-w-[92%] sm:max-w-[78%]',
            'rounded-2xl px-4 py-3.5',
            'bg-primary/[0.07] ring-1 ring-primary/10' => $ownSide,
            'bg-base-200/65 ring-1 ring-base-300/50' => ! $ownSide,
        ])
    >
        <div class="flex items-center gap-2">
            <span
                @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-lg',
                    'bg-primary/10 text-primary' => $fromAdmin,
                    'bg-base-100 text-base-content/45' => ! $fromAdmin,
                ])
            >
                <x-icon
                    :name="$fromAdmin
                        ? 'lucide.headset'
                        : 'lucide.user-round'"
                    class="!size-3.5 stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">
                <div class="truncate text-xs font-semibold text-base-content/75">
                    {{ $authorLabel }}
                </div>

                <time
                    class="mt-0.5 block text-[10px] text-base-content/35"
                    datetime="{{ $message->created_at?->toIso8601String() }}"
                >
                    {{ $message->created_at?->format('Y-m-d H:i') }}
                </time>
            </div>
        </div>

        <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-base-content/75">{{ $message->body }}</p>
    </article>
</div>
