@if($renderStableMobileCta ?? false)
    @php
        $quoteTargets = 'selectRegionGroup,selectRegion,selectSize,selectPeriod,decreaseDisk,increaseDisk,retryQuote';
        $ctaReady = $catalogLoaded
            && ! ($catalogError !== null && $regions === []);
        $purchaseDisabled = ! $ctaReady
            || $quote === []
            || $catalogError !== null
            || $quoteError !== null;
    @endphp

    <div
        wire:key="buy-mobile-cta-root"
        data-buy-mobile-action
        @class([
            '
                fixed! inset-x-0 bottom-0 z-50
                px-3 pt-2
                pb-[max(0.75rem,env(safe-area-inset-bottom))]
                md:hidden!
            ',
            'hidden!' => ! $ctaReady,
        ])
        role="region"
        aria-label="پرداخت سفارش"
        aria-hidden="{{ $ctaReady ? 'false' : 'true' }}"
    >
        @if($ctaReady)
            <div
                class="
                    mx-auto w-full max-w-xl
                    rounded-2xl
                    border border-base-300/80
                    bg-base-100/95
                    p-3
                    shadow-[0_-10px_35px_-18px_rgb(0_0_0_/_0.35)]
                    backdrop-blur-md
                "
            >
                <div
                    class="
                        grid
                        grid-cols-[minmax(0,1fr)_auto]
                        items-center gap-3
                    "
                >
                    <div class="min-w-0">
                        <div
                            class="
                                text-[10px] font-medium
                                text-base-content/40
                            "
                        >
                            مبلغ نهایی
                        </div>

                        <div
                            class="mt-0.5 min-h-7"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <div
                                wire:loading.remove
                                wire:target="{{ $quoteTargets }}"
                            >
                                @if($quote !== [])
                                    <div
                                        class="
                                            flex min-w-0
                                            items-baseline gap-1.5
                                            text-base-content
                                        "
                                    >
                                        <span
                                            dir="ltr"
                                            class="
                                                technical-value
                                                truncate
                                                text-xl font-semibold
                                                tracking-tight
                                            "
                                        >
                                            {{ $this->formatToman(
                                                (int) $quote['final_amount']
                                            ) }}
                                        </span>

                                        <span
                                            class="
                                                shrink-0 text-[11px]
                                                text-base-content/45
                                            "
                                        >
                                            تومان
                                        </span>
                                    </div>
                                @else
                                    <span
                                        class="
                                            text-sm font-medium
                                            text-base-content/35
                                        "
                                    >
                                        —
                                    </span>
                                @endif
                            </div>

                            <div
                                wire:loading
                                wire:target="{{ $quoteTargets }}"
                                class="
                                    flex items-center gap-1.5
                                    text-[11px]
                                    text-base-content/45
                                "
                            >
                                <span
                                    class="
                                        loading loading-spinner
                                        loading-xs text-primary
                                    "
                                ></span>

                                به‌روزرسانی قیمت
                            </div>
                        </div>
                    </div>

                    <x-button
                        label="پرداخت"
                        icon="lucide.credit-card"
                        wire:click="purchase"
                        wire:target="purchase"
                        wire:loading.attr="disabled"
                        spinner
                        :disabled="$purchaseDisabled"
                        class="
                            btn-primary
                            h-11 min-h-11
                            min-w-28 shrink-0
                            rounded-xl px-5
                            text-sm font-semibold
                            shadow-sm
                        "
                    />
                </div>
            </div>
        @endif
    </div>
@endif
