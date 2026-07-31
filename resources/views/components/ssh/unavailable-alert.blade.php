@props([
    'message' => 'ارتباط SSH با سرور برقرار نشد.',
    'retryAfter' => null,
    'retryAction' => 'retryConnection',
])

<x-alert
    icon="o-signal-slash"
    class="border border-error/20 bg-error/5"
>
    <div
        class="flex w-full flex-col gap-5
               sm:flex-row sm:items-center sm:justify-between"
    >

        <div class="min-w-0">

            <h3 class="font-semibold text-error">
                ارتباط با سرور قطع شده است
            </h3>

            <p class="mt-1 text-sm leading-7 text-base-content/65">
                {{ $message }}
            </p>

            @if (
              is_int($retryAfter)
              && $retryAfter > 0
          )

                <p class="mt-2 text-xs leading-6 text-base-content/45">
                    تلاش‌های خودکار برای جلوگیری از درخواست‌های مکرر موقتاً
                    متوقف شده‌اند. می‌توانید اکنون به‌صورت دستی تلاش کنید یا حدود

                    <span class="font-medium text-base-content/70">
            {{ $retryAfter }} ثانیه
        </span>

                    منتظر بمانید.
                </p>

            @else

                <p class="mt-2 text-xs leading-6 text-base-content/45">
                    وضعیت سرور، پورت SSH، سرویس SSH و اطلاعات ورود را بررسی کنید.
                </p>

            @endif

        </div>

        <x-button
            label="تلاش مجدد"
            icon="o-arrow-path"
            wire:click="{{ $retryAction }}"
            wire:target="{{ $retryAction }}"
            wire:loading.attr="disabled"
            spinner="{{ $retryAction }}"
            class="btn-error btn-outline shrink-0"
        />

    </div>
</x-alert>
