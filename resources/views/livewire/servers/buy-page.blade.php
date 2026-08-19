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
    @class([
        'cloud-purchase-page',
        'cloud-purchase-page--fixed-disk' => ! $customDiskEnabled,
        'cloud-purchase-page--multi-provider' => count($providers) > 1,
    ])
>
    <style>
        .cloud-purchase-page
        [data-buy-content] > [dir="rtl"] > header {
            display: none;
        }

        .cloud-purchase-page--multi-provider
        [data-buy-content] > [dir="rtl"] > div.grid > main > section {
            border-top: 0;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }

        .cloud-purchase-page
        [data-buy-content]
        :where(button, select)[data-loading] {
            pointer-events: none;
            opacity: 0.6;
        }

        @media (max-width: 1279px) {
            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"] {
                padding-bottom:
                    calc(
                        7rem
                        + env(safe-area-inset-bottom, 0px)
                    );
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0 {
                right: 0.75rem;
                bottom:
                    calc(
                        0.75rem
                        + env(safe-area-inset-bottom, 0px)
                    );
                left: 0.75rem;

                border-top: 0;
                background: transparent;
                padding: 0;

                -webkit-backdrop-filter: none;
                backdrop-filter: none;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0 > div {
                border: 1px solid var(--color-base-300);
                border-radius: 1rem;

                background:
                    color-mix(
                        in srgb,
                        var(--color-base-100) 94%,
                        transparent
                    );

                padding: 0.75rem 0.875rem;

                box-shadow:
                    0 18px 44px
                    color-mix(
                        in srgb,
                        var(--color-base-content) 12%,
                        transparent
                    );

                -webkit-backdrop-filter: blur(18px);
                backdrop-filter: blur(18px);
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0
            > div > div.min-w-0.flex-1 > div:first-child {
                margin-bottom: 0.125rem;

                color:
                    color-mix(
                        in srgb,
                        var(--color-base-content) 50%,
                        transparent
                    );

                font-size: 0.625rem;
                font-weight: 500;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0
            span[dir="ltr"] {
                overflow: visible;

                font-family:
                    ui-monospace,
                    SFMono-Regular,
                    Menlo,
                    Monaco,
                    Consolas,
                    "Liberation Mono",
                    "Courier New",
                    monospace;

                font-size: 1.125rem;
                font-weight: 700;
                line-height: 1.35;
                letter-spacing: -0.025em;

                text-overflow: clip;
                font-variant-numeric: tabular-nums;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0
            [wire\:click="purchase"] {
                min-width: 7.5rem;
                min-height: 2.75rem;

                border-radius: 0.875rem;
                padding-inline: 1rem;

                font-size: 0.875rem;
                font-weight: 600;

                box-shadow:
                    0 6px 18px
                    color-mix(
                        in srgb,
                        var(--color-primary) 20%,
                        transparent
                    );
            }
        }

        @media (max-width: 639px) {
            .cloud-purchase-page [data-buy-provider-row]
            [data-provider-option] {
                min-height: 4.25rem;
                padding: 0.625rem;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.grid > main > section > div {
                gap: 0.625rem;
                padding: 0.875rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            button[wire\:click^="selectRegionGroup"] {
                min-height: 2.75rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            select[wire\:change^="selectRegion"] {
                height: 2.75rem;
                min-height: 2.75rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            div:has(> button[wire\:click^="selectPeriod"]) {
                gap: 0.375rem;
                padding: 0.375rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            button[wire\:click^="selectPeriod"] {
                min-height: 3.75rem;
                padding: 0.5rem 0.25rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            button[wire\:click^="selectPeriod"] > div:first-of-type {
                white-space: nowrap;
                font-size: 0.75rem;
                line-height: 1rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            select[wire\:change^="selectSize"] {
                height: 2.75rem;
                min-height: 2.75rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            .tooltip[data-tip] > div {
                flex-direction: column;
                gap: 0.25rem;
                padding: 0.625rem 0.25rem;
                text-align: center;
            }

            .cloud-purchase-page
            [data-buy-content]
            .tooltip[data-tip] > div > div {
                min-width: 0;
                text-align: center;
            }

            .cloud-purchase-page
            [data-buy-content]
            input[name="server_image"] + span {
                height: 2.75rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            div:has(> [wire\:click="decreaseDisk"]) {
                height: 2.75rem;
            }

            .cloud-purchase-page
            [data-buy-content]
            :where(
                [wire\:click="decreaseDisk"],
                [wire\:click="increaseDisk"]
            ) {
                min-width: 2.75rem;
                min-height: 2.75rem;
            }
        }

        @media (max-width: 359px) {
            .cloud-purchase-page
            [data-buy-content]
            button[wire\:click^="selectPeriod"] > span:first-child {
                display: none;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0 > div {
                gap: 0.5rem;
                padding: 0.625rem 0.75rem;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0
            span[dir="ltr"] {
                font-size: 1rem;
            }

            .cloud-purchase-page
            [data-buy-content] > [dir="rtl"]
            > div.fixed.inset-x-0.bottom-0
            [wire\:click="purchase"] {
                min-width: 6.75rem;
                padding-inline: 0.75rem;
            }
        }
    </style>

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

    <div
        data-buy-workspace-toolbar
        class="mb-3 flex items-center justify-between gap-3"
    >
        <div
            class="
                flex min-w-0 items-center gap-2
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

            <span>سرور جدید</span>
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
        <div
            class="
                grid grid-cols-1 gap-4
                xl:grid-cols-[minmax(0,1fr)_320px]
            "
        >
            <section
                data-buy-provider-row
                class="
                    overflow-hidden
                    rounded-2xl rounded-b-none
                    border border-b-0 border-base-300
                    bg-base-100
                "
            >
                <div
                    class="
                        grid gap-3 p-4
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
                            grid w-full grid-cols-2 gap-2
                            sm:max-w-xl
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
                                        px-3 py-2.5
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
                                        border-base-300 bg-base-100
                                        hover:border-primary/30
                                        hover:bg-primary/[0.025]
                                    ' => ! $isSelected,
                                ])
                                x-bind:class="{
                                    'border-primary/60 bg-primary/[0.065] ring-2 ring-primary/15':
                                        switchingProvider
                                        && pendingProvider === $el.dataset.providerOption,
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
                                                bg-primary/10 text-primary
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

                                    <span
                                        x-show="
                                            switchingProvider
                                            && pendingProvider === $el.closest('[data-provider-option]').dataset.providerOption
                                        "
                                        style="display: none"
                                        class="
                                            shrink-0 text-[9px]
                                            font-medium text-primary
                                        "
                                    >
                                        در حال تغییر
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            <div
                class="hidden xl:block"
                aria-hidden="true"
            ></div>
        </div>
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
