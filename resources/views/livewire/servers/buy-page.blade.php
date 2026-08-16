<div dir="rtl">
    @if(count($providers) > 1)
        <section
            class="
                mb-4 flex flex-col gap-3
                rounded-2xl
                border border-base-300
                bg-base-100
                p-3.5
                sm:flex-row sm:items-center sm:justify-between
            "
        >
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span
                        class="
                            flex size-8 shrink-0
                            items-center justify-center
                            rounded-lg
                            bg-primary/8
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.cloud-cog"
                            class="!size-4"
                        />
                    </span>

                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-base-content">
                            ارائه‌دهنده زیرساخت
                        </div>

                        <div class="mt-0.5 text-xs text-base-content/45">
                            قیمت، موقعیت و پلن‌ها بر اساس ارائه‌دهنده انتخابی دریافت می‌شوند.
                        </div>
                    </div>
                </div>
            </div>

            <label class="form-control w-full shrink-0 sm:w-56">
                <span class="sr-only">انتخاب ارائه‌دهنده زیرساخت</span>

                <select
                    class="select select-bordered select-sm w-full rounded-xl"
                    wire:change="selectProvider($event.target.value)"
                    wire:target="selectProvider"
                    wire:loading.attr="disabled"
                    aria-label="انتخاب ارائه‌دهنده زیرساخت"
                >
                    @foreach($providers as $providerOption)
                        <option
                            value="{{ $providerOption['id'] }}"
                            @selected($providerOption['id'] === $provider)
                        >
                            {{ $providerOption['label'] }}
                        </option>
                    @endforeach
                </select>
            </label>
        </section>
    @endif

    @include('livewire.servers.buy')
</div>
