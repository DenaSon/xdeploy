<div
    dir="rtl"
    data-current-provider="{{ $provider }}"
    data-buy-workspace
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
                this.currentProvider = provider;
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

                        <button
                            type="button"
                            data-provider-option="{{ $providerOption['id'] }}"
                            x-on:click="selectProvider($el.dataset.providerOption)"
                            x-bind:disabled="switchingProvider"
                            wire:loading.attr="disabled"
                            wire:target="selectProvider"
                            @class([
                                '
                                    group min-w-0
                                    cursor-pointer
                                    rounded-xl border
                                    px-3 py-3
                                    text-right
                                    transition-all duration-150
                                    disabled:cursor-wait
                                ',
                                '
                                    border-primary/45
                                    bg-primary/[0.055]
                                    ring-1 ring-primary/10
                                ' => $isSelected,
                                '
                                    border-base-300
                                    bg-base-100
                                    hover:border-primary/30
                                    hover:bg-primary/[0.025]
                                ' => ! $isSelected,
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
                                            border-primary/20
                                            bg-primary/10
                                            text-primary
                                        ' => $isSelected,
                                        '
                                            border-base-300
                                            bg-base-200/55
                                            text-base-content/45
                                        ' => ! $isSelected,
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
                                            :name="$isSelected ? 'lucide.check' : 'lucide.cloud'"
                                            class="!size-3.5"
                                        />
                                    </span>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="
                                            truncate text-sm font-semibold
                                            text-base-content
                                        "
                                    >
                                        {{ $providerOption['label'] }}
                                    </div>

                                    <div
                                        class="
                                            mt-0.5 text-[10px]
                                            text-base-content/40
                                        "
                                    >
                                        {{ $isSelected ? 'انتخاب‌شده' : 'انتخاب زیرساخت' }}
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

        @include('livewire.servers.buy')
    </div>
</div>
