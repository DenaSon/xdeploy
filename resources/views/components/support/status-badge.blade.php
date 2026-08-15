@props([
    'status',
])

@php
    use App\Domain\Support\Enums\SupportRequestStatus;

    $resolved = $status instanceof SupportRequestStatus
        ? $status
        : SupportRequestStatus::tryFrom((string) $status);

    [$label, $classes, $dot] = match ($resolved) {
        SupportRequestStatus::Open => [
            'باز',
            'bg-warning/[0.09] text-warning',
            'bg-warning',
        ],
        SupportRequestStatus::Answered => [
            'پاسخ داده‌شده',
            'bg-primary/[0.09] text-primary',
            'bg-primary',
        ],
        SupportRequestStatus::Closed => [
            'بسته',
            'bg-base-200 text-base-content/45',
            'bg-base-content/30',
        ],
        default => [
            'نامشخص',
            'bg-base-200 text-base-content/45',
            'bg-base-content/30',
        ],
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-medium',
        $classes,
    ]) }}
>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
