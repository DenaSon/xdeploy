@props(['status'])

@php
    $value = $status instanceof \BackedEnum
        ? $status->value
        : (string) $status;

    [$label, $class] = match ($value) {
        'active' => ['فعال', 'badge-success'],
        'inactive' => ['غیرفعال', 'badge-ghost'],
        'pending_payment' => ['در انتظار پرداخت', 'badge-warning'],
        'paid' => ['پرداخت‌شده', 'badge-success'],
        'provisioning' => ['در حال آماده‌سازی', 'badge-info'],
        'fulfilled' => ['تکمیل‌شده', 'badge-success'],
        'initiating' => ['در حال آغاز', 'badge-info'],
        'pending' => ['در انتظار', 'badge-warning'],
        'failed' => ['ناموفق', 'badge-error'],
        'cancelled' => ['لغوشده', 'badge-ghost'],
        'expired' => ['منقضی', 'badge-ghost'],
        default => [$value, 'badge-ghost'],
    };
@endphp

<span {{ $attributes->class(['badge badge-sm', $class]) }}>
    {{ $label }}
</span>
