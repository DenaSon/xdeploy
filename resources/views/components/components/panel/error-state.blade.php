@props([
    'message' =>
        'خطایی در بارگذاری اطلاعات رخ داد.',
])

<div
    class="
        alert
        alert-error
    "
>

    <x-icon
        name="o-exclamation-triangle"
        class="w-5 h-5"
    />

    <span>
        {{ $message }}
    </span>

</div>
