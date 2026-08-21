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
                dock dock-xl z-50
                border-t border-base-300
                bg-base-100/95
                shadow-[0_-10px_28px_-20px_rgb(0_0_0_/_0.45)]
                backdrop-blur-md
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
                    mx-auto flex h-full w-full
                    max-w-xl items-center gap-3
                    px-4
                "
            >
                <div class="min-w-0 flex-1">
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
                                        items-baseline gap-1
                                        text-base-content
                                    "
                                >
                                    <span
                                        dir="ltr"
                                        class="
                                            technical-value
                                            truncate
                                            text-lg font-semibold
                                            tracking-tight
                                        "
                                    >
                                        {{ $this->formatToman(
                                            (int) $quote['final_amount']
                                        ) }}
                                    </span>

                                    <span
                                        class="
                                            shrink-0 text-[10px]
                                            text-base-content/40
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
                                text-[10px]
                                text-base-content/40
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
                    label="پرداخت و ساخت"
                    icon="lucide.credit-card"
                    wire:click="purchase"
                    wire:target="purchase"
                    wire:loading.attr="disabled"
                    spinner
                    :disabled="$purchaseDisabled"
                    class="
                        btn-primary
                        h-11 min-h-11
                        w-[44%] max-w-44
                        shrink-0 rounded-xl
                        px-3 text-xs
                    "
                />
            </div>
        @endif
    </div>
@endif
