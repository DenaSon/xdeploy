@props([
    'date' => null,
    'fallback' => '—',
])

@if ($date instanceof \DateTimeInterface)
    <time
        datetime="{{ $date->format(DATE_ATOM) }}"
        {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}
    >
        {{ \App\Support\Date\JalaliDateFormatter::dateTime(
            $date,
            separator: ' — ',
            persianDigits: true,
        ) }}
    </time>
@else
    <span {{ $attributes }}>{{ $fallback }}</span>
@endif
