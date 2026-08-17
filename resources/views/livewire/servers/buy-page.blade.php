<div
    dir="rtl"
    data-current-provider="{{ $provider }}"
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
    @class([
        'cloud-purchase-page',
        'cloud-purchase-page--fixed-disk' => ! $customDiskEnabled,
    ])
>
    @if(! $customDiskEnabled)
        <style>
            .cloud-purchase-page--fixed-disk
            .grid:has(> div > [wire\:click="decreaseDisk"]) {
                grid-template-columns: minmax(0, 1fr);
            }

            .cloud-purchase-page--fixed-disk
            div:has(> [wire\:click="decreaseDisk"]) {
                display: none;
            }
        </style>
    @endif

    @if(count($providers) > 0)
        <section
            class="
                mb-4 rounded-2xl
                border border-base-300
                bg-base-100
                p-3
                sm:p-3.5
            "
        >
            <div
                class="
                    flex flex-col gap-3
                    lg:flex-row lg:items-center lg:justify-between
                "
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="
                                flex size-9 shrink-0
                                items-center justify-center
                                rounded-xl
                                border border-primary/15
                                bg-primary/5
                                text-primary
                            "
                        >
                            <x-icon
                                name="lucide.layers-3"
                                class="!size-4"
                            />
                        </span>

                        <div class="min-w-0">
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
                                        loading
                                        loading-spinner
                                        loading-xs
                                        text-primary
                                    "
                                ></span>
                            </div>

                            <div
                                class="
                                    mt-0.5 text-xs
                                    text-base-content/45
                                "
                            >
                                موقعیت، پلن و قیمت بر اساس زیرساخت انتخابی دریافت می‌شوند.
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($providers) > 1)
                    <div
                        class="
                            grid w-full grid-cols-2 gap-2
                            lg:w-auto lg:min-w-[360px]
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
                                        group relative
                                        min-w-0 rounded-xl
                                        border px-3 py-2.5
                                        text-right
                                        transition-all duration-150
                                    ',
                                    '
                                        border-primary/45
                                        bg-primary/[0.055]
                                        ring-1 ring-primary/10
                                    ' => $isSelected,
                                    '
                                        border-base-300
                                        bg-base-100
                                        hover:border-primary/25
                                        hover:bg-base-200/35
                                    ' => ! $isSelected,
                                ])
                                x-bind:class="{
                                    'border-primary/60 bg-primary/[0.065] ring-2 ring-primary/15':
                                        switchingProvider
                                        && pendingProvider === $el.dataset.providerOption,
                                    'opacity-55':
                                        switchingProvider
                                        && pendingProvider !== $el.dataset.providerOption,
                                    'cursor-wait': switchingProvider,
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
                                                loading
                                                loading-spinner
                                                loading-xs
                                                text-primary
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
                                                mt-0.5 truncate
                                                text-[10px]
                                                text-base-content/40
                                            "
                                        >
                                            آماده خرید
                                        </div>
                                    </div>

                                    <span
                                        @class([
                                            '
                                                shrink-0 rounded-full
                                                px-2 py-1
                                                text-[9px] font-medium
                                            ',
                                            'bg-primary/10 text-primary' => $isSelected,
                                            'bg-base-200/70 text-base-content/35' => ! $isSelected,
                                        ])
                                    >
                                        <span
                                            x-show="
                                                switchingProvider
                                                && pendingProvider === $el.closest('[data-provider-option]').dataset.providerOption
                                            "
                                            style="display: none"
                                        >
                                            در حال تغییر
                                        </span>

                                        <span
                                            x-show="
                                                ! switchingProvider
                                                || pendingProvider !== $el.closest('[data-provider-option]').dataset.providerOption
                                            "
                                        >
                                            {{ $isSelected ? 'انتخاب‌شده' : 'انتخاب' }}
                                        </span>
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    @php($providerOption = $providers[0])

                    <div
                        class="
                            flex w-full items-center gap-2.5
                            rounded-xl
                            border border-primary/20
                            bg-primary/[0.045]
                            px-3 py-2.5
                            lg:w-auto lg:min-w-48
                        "
                    >
                        <span
                            class="
                                flex size-8 shrink-0
                                items-center justify-center
                                rounded-lg
                                bg-primary/10
                                text-primary
                            "
                        >
                            <x-icon
                                name="lucide.cloud"
                                class="!size-3.5"
                            />
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
                                زیرساخت فعال
                            </div>
                        </div>

                        <span
                            class="
                                rounded-full
                                bg-primary/10
                                px-2 py-1
                                text-[9px] font-medium
                                text-primary
                            "
                        >
                            فعال
                        </span>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div
        class="relative"
        wire:loading.class="pointer-events-none select-none"
        wire:target="selectProvider"
    >
        <div
            data-provider-switch-overlay
            x-show="switchingProvider"
            style="display: none"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="
                absolute inset-0 z-50
                flex items-start justify-center
                rounded-2xl
                bg-base-100/65
                pt-8
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
                        loading
                        loading-spinner
                        loading-sm
                        text-primary
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
