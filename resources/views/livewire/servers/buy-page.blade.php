<div
    dir="rtl"
    data-current-provider="{{ $provider }}"
    data-buy-workspace
    wire:poll.30s="refreshProviderReadiness"
    x-data="{
        switchingProvider: false,
        pendingProvider: null,
        currentProvider: $el.dataset.currentProvider,

        async selectProvider(provider) {
            if (
                this.switchingProvider
                || provider === this.currentProvider
            ) {
                return;
            }

            this.pendingProvider = provider;
            this.switchingProvider = true;

            try {
                await this.$wire.selectProvider(provider);
                this.currentProvider = $el.dataset.currentProvider;
            } finally {
                this.switchingProvider = false;
                this.pendingProvider = null;
            }
        },
    }"
    class="space-y-3"
>
    <div
        class="
            flex items-center
            justify-between gap-3
        "
    >
        <div
            class="
                flex min-w-0
                items-center gap-2
                text-xs font-medium
                text-base-content/45
            "
        >
            <span
                class="
                    flex size-7 shrink-0
                    items-center justify-center
                    rounded-lg
                    bg-base-200/70
                    text-base-content/45
                "
            >
                <x-icon
                    name="lucide.server-cog"
                    class="!size-3.5"
                />
            </span>

            <span>خرید VPS</span>
        </div>

        <x-button
            label="سرورها"
            icon="lucide.arrow-right"
            :link="route('panel.servers.index')"
            wire:navigate
            aria-label="بازگشت به سرورها"
            class="
                btn-ghost btn-sm
                h-11 min-h-11
                rounded-lg px-3
                text-xs font-medium
                text-base-content/45
                hover:text-base-content
                sm:h-8 sm:min-h-8 sm:px-2.5
            "
        />
    </div>

    @if(count($providers) > 1)
        <section
            data-buy-provider-row
            class="
                rounded-2xl
                border border-base-300
                bg-base-100
                p-4
            "
        >
            <div
                class="
                    grid gap-3
                    lg:grid-cols-[140px_minmax(0,1fr)]
                    lg:items-center
                "
            >
                <div>
                    <div
                        class="
                            flex items-center gap-2
                            text-sm font-semibold
                            text-base-content
                        "
                    >
                        زیرساخت

                        <span
                            wire:loading.delay.shorter
                            wire:target="selectProvider"
                            class="
                                loading loading-spinner
                                loading-xs text-primary
                            "
                        ></span>
                    </div>

                    <div
                        class="
                            mt-0.5 text-xs
                            text-base-content/40
                        "
                    >
                        بستر اجرای سرور
                    </div>
                </div>

                <div
                    class="
                        grid w-full
                        grid-cols-1 gap-2
                        sm:grid-cols-2
                        lg:max-w-xl
                    "
                >
                    @foreach($providers as $providerOption)
                        @php($isSelected = $providerOption['id'] === $provider)
                        @php($isAvailable = (bool) ($providerOption['available'] ?? false))

                        <button
                            type="button"
                            data-provider-option="{{ $providerOption['id'] }}"
                            x-on:click="selectProvider($el.dataset.providerOption)"
                            x-bind:disabled="switchingProvider || {{ $isAvailable ? 'false' : 'true' }}"
                            wire:loading.attr="disabled"
                            wire:target="selectProvider"
                            @class([
                                '
                                    group min-w-0
                                    rounded-xl border
                                    px-3 py-3
                                    text-right
                                    transition-all duration-150
                                ',
                                '
                                    cursor-pointer
                                    disabled:cursor-wait
                                ' => $isAvailable,
                                '
                                    cursor-not-allowed
                                    border-base-300/70
                                    bg-base-200/30
                                    opacity-60
                                ' => ! $isAvailable,
                                '
                                    border-primary
                                    bg-primary
                                    text-primary-content
                                    ring-2 ring-primary/15
                                    shadow-sm
                                ' => $isSelected && $isAvailable,
                                '
                                    border-base-300
                                    bg-base-100
                                    hover:border-primary/30
                                    hover:bg-primary/[0.025]
                                ' => ! $isSelected && $isAvailable,
                            ])
                            x-bind:class="{
                                'opacity-55':
                                    switchingProvider
                                    && pendingProvider !== $el.dataset.providerOption,
                            }"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    @class([
                                        '
                                            flex size-8 shrink-0
                                            items-center justify-center
                                            rounded-lg border
                                        ',
                                        '
                                            border-primary-content/20
                                            bg-primary-content/15
                                            text-primary-content
                                        ' => $isSelected && $isAvailable,
                                        '
                                            border-base-300
                                            bg-base-200/55
                                            text-base-content/40
                                        ' => ! $isAvailable || (! $isSelected && $isAvailable),
                                    ])
                                >
                                    <span
                                        x-show="
                                            switchingProvider
                                            && pendingProvider === $el.closest('[data-provider-option]').dataset.providerOption
                                        "
                                        style="display: none"
                                        class="
                                            loading loading-spinner
                                            loading-xs text-primary
                                        "
                                    ></span>

                                    <span
                                        x-show="
                                            ! switchingProvider
                                            || pendingProvider !== $el.closest('[data-provider-option]').dataset.providerOption
                                        "
                                    >
                                        <x-icon
                                            :name="! $isAvailable
                                                ? 'lucide.circle-off'
                                                : 'lucide.mountain'"
                                            class="!size-3.5"
                                        />
                                    </span>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div
                                        @class([
                                            '
                                                flex min-w-0 items-center gap-2
                                                text-sm font-semibold
                                            ',
                                            'text-primary-content' => $isSelected && $isAvailable,
                                            'text-base-content' => ! $isSelected || ! $isAvailable,
                                        ])
                                    >
                                        <span class="truncate">
                                            {{ $providerOption['label'] }}
                                        </span>

                                        @if($isSelected && $isAvailable)
                                            <span
                                                class="
                                                    shrink-0 rounded-md
                                                    bg-primary-content/15
                                                    px-1.5 py-0.5
                                                    text-[9px] font-medium
                                                    text-primary-content
                                                "
                                            >
                                                انتخاب‌شده
                                            </span>
                                        @endif

                                        @if(! $isAvailable)
                                            <span
                                                class="
                                                    shrink-0 rounded-md
                                                    bg-base-200 px-1.5 py-0.5
                                                    text-[9px] font-medium
                                                    text-base-content/45
                                                "
                                            >
                                                فعلاً در دسترس نیست
                                            </span>
                                        @endif
                                    </div>

                                    <div
                                        @class([
                                            'mt-0.5 text-[10px]',
                                            'text-base-content/35' => ! $isAvailable,
                                            'text-primary-content/70' => $isSelected && $isAvailable,
                                            'text-base-content/40' => ! $isSelected && $isAvailable,
                                        ])
                                    >
                                        @if(! $isAvailable)
                                            خرید جدید از این زیرساخت فعلاً در دسترس نیست.
                                        @else
                                            {{ $providerOption['description'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div
        data-buy-content
        class="relative"
        wire:loading.class="pointer-events-none select-none"
        wire:target="selectProvider"
    >
        <div
            data-provider-switch-overlay
            x-show="switchingProvider"
            style="display: none"
            x-transition.opacity.duration.150ms
            class="
                absolute inset-0 z-50
                flex items-start justify-center
                rounded-2xl
                bg-base-100/65 pt-8
                backdrop-blur-[1px]
                sm:items-center sm:pt-0
            "
            role="status"
            aria-live="polite"
            aria-label="در حال تغییر زیرساخت"
        >
            <div
                class="
                    flex items-center gap-2.5
                    rounded-xl
                    border border-base-300
                    bg-base-100
                    px-3.5 py-2.5
                    shadow-sm
                "
            >
                <span
                    class="
                        loading loading-spinner
                        loading-sm text-primary
                    "
                ></span>

                <div>
                    <div
                        class="
                            text-xs font-semibold
                            text-base-content
                        "
                    >
                        در حال تغییر زیرساخت
                    </div>

                    <div
                        class="
                            mt-0.5 text-[10px]
                            text-base-content/40
                        "
                    >
                        دریافت موقعیت‌ها، پلن‌ها و قیمت جدید
                    </div>
                </div>
            </div>
        </div>

        @include(
            'livewire.servers.partials.buy-mobile-cta',
            ['renderStableMobileCta' => true]
        )

        @include('livewire.servers.buy')
    </div>
</div>