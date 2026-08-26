@props([
    'date' => null,
    'withTime' => true,
    'fallback' => '—',
])

@php
    $formatted = \App\Support\Localization\PersianDate::format(
        $date,
        withTime: (bool) $withTime,
    );
@endphp

@if ($date instanceof \DateTimeInterface)
    <time
        datetime="{{ $date->format(DATE_ATOM) }}"
        {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}
    >
        {{ $formatted ?? $fallback }}
    </time>
@else
    <span {{ $attributes }}>{{ $fallback }}</span>
@endif
