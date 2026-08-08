@props([
    'title' => 'دریافت اطلاعات ناموفق بود',
    'message',
    'retryAction' => 'reload',
])

<x-dashboard.card
    :title="$title"
    subtitle="Widget unavailable"
    icon="o-exclamation-triangle"
    class="w-full min-w-0"
>
    <div
        class="rounded-2xl border border-error/15
               bg-error/5 p-4"
    >
        <div class="flex items-start gap-3">
            <x-icon
                name="o-exclamation-triangle"
                class="mt-0.5 size-5 shrink-0 text-error"
            />

            <div class="min-w-0 flex-1">
                <p
                    class="text-sm leading-7
                           text-base-content/65"
                >
                    {{ $message }}
                </p>

                <x-button
                    label="تلاش مجدد"
                    icon="o-arrow-path"
                    wire:click="{{ $retryAction }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $retryAction }}"
                    spinner="{{ $retryAction }}"
                    class="btn-error btn-outline btn-sm mt-4"
                />
            </div>
        </div>
    </div>
</x-dashboard.card>
