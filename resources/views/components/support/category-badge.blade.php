@props([
    'category',
])

@php
    use App\Domain\Support\Enums\SupportRequestCategory;

    $resolved = $category instanceof SupportRequestCategory
        ? $category
        : SupportRequestCategory::tryFrom((string) $category);

    [$label, $icon] = match ($resolved) {
        SupportRequestCategory::Technical => ['فنی', 'lucide.wrench'],
        SupportRequestCategory::Billing => ['مالی و پرداخت', 'lucide.credit-card'],
        SupportRequestCategory::Account => ['حساب کاربری', 'lucide.user-round'],
        SupportRequestCategory::Other => ['سایر', 'lucide.message-square-more'],
        default => ['سایر', 'lucide.message-square-more'],
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-full bg-base-200/70 px-2.5 py-1 text-[10px] font-medium text-base-content/55',
    ]) }}
>
    <x-icon
        :name="$icon"
        class="!size-3 stroke-[1.8]"
    />

    {{ $label }}
</span>
