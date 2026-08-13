<section
    class="overflow-hidden rounded-2xl
           border border-base-300
           bg-base-100"
>
    <header
        class="flex flex-col gap-4
               border-b border-base-300
               px-5 py-5
               sm:flex-row
               sm:items-start
               sm:justify-between
               sm:px-6"
    >
        <div class="flex items-start gap-3.5">
            <div
                class="flex size-10 shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-primary/[0.07]
                       text-primary"
            >
                <x-icon
                    name="lucide.shield"
                    class="size-4.5"
                />
            </div>

            <div>
                <h2 class="text-base font-semibold text-base-content">
                    دستگاه‌های AmneziaWG
                </h2>

                <p
                    class="mt-1 max-w-2xl
                           text-sm leading-7
                           text-base-content/55"
                >
                    برای هر دستگاه یک Peer مستقل با IP اختصاصی ساخته می‌شود.
                    تغییرات بدون راه‌اندازی مجدد کانتینر روی runtime اعمال می‌شوند.
                </p>
            </div>
        </div>

        <x-button
            label="بروزرسانی وضعیت"
            icon="lucide.refresh-cw"
            wire:click="refreshPeers"
            spinner="refreshPeers"
            class="btn-ghost btn-sm rounded-xl"
        />
    </header>

    <div class="border-b border-base-300 px-5 py-5 sm:px-6">
        <x-form
            wire:submit="createPeer"
            no-separator
        >
            <div
                class="grid grid-cols-1 gap-4
                       md:grid-cols-[minmax(0,1fr)_auto]
                       md:items-end"
            >
                <x-input
                    label="نام دستگاه"
                    wire:model.blur="peerName"
                    icon="lucide.smartphone"
                    placeholder="مثلاً iPhone"
                    hint="این نام فقط برای شناسایی دستگاه در xDeploy استفاده می‌شود."
                    maxlength="60"
                    wire:loading.attr="disabled"
                    wire:target="createPeer"
                />

                <x-button
                    label="افزودن دستگاه"
                    icon="lucide.plus"
                    type="submit"
                    spinner="createPeer"
                    wire:loading.attr="disabled"
                    wire:target="createPeer"
                    class="btn-primary btn-sm rounded-xl"
                />
            </div>
        </x-form>
    </div>

    @if (! $runtimeAvailable)
        <div
            class="flex items-start gap-3
                   border-b border-base-300
                   bg-warning/[0.035]
                   px-5 py-4
                   sm:px-6"
        >
            <x-icon
                name="lucide.triangle-alert"
                class="mt-0.5 size-4 shrink-0 text-warning"
            />

            <p class="text-sm leading-6 text-base-content/55">
                وضعیت runtime دستگاه‌ها از سرور دریافت نشد.
                اتصال سرور و اجرای AmneziaWG را بررسی کنید و دوباره بروزرسانی بزنید.
            </p>
        </div>
    @endif

    <div class="divide-y divide-base-300">
        @forelse ($peers as $peer)
            @php
                $handshakeAt = $peer['latest_handshake_at'] ?? null;
                $handshakeLabel = is_int($handshakeAt) && $handshakeAt > 0
                    ? \Carbon\CarbonImmutable::createFromTimestamp($handshakeAt)
                        ->setTimezone(config('app.timezone'))
                        ->diffForHumans()
                    : 'هنوز متصل نشده';
            @endphp

            <article
                class="flex flex-col gap-4
                       px-5 py-4
                       sm:flex-row
                       sm:items-center
                       sm:justify-between
                       sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-9 shrink-0
                               items-center justify-center
                               rounded-xl bg-base-200/70"
                    >
                        <x-icon
                            name="lucide.smartphone"
                            class="size-4 text-base-content/50"
                        />
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium text-base-content">
                                {{ $peer['name'] }}
                            </p>

                            @if ($peer['runtime_configured'])
                                <span
                                    class="inline-flex items-center gap-1.5
                                           rounded-full border border-success/20
                                           bg-success/10 px-2 py-0.5
                                           text-[11px] font-medium text-success"
                                >
                                    <span class="size-1.5 rounded-full bg-success"></span>
                                    فعال
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1.5
                                           rounded-full border border-warning/20
                                           bg-warning/10 px-2 py-0.5
                                           text-[11px] font-medium text-warning"
                                >
                                    همگام نیست
                                </span>
                            @endif
                        </div>

                        <div
                            class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1
                                   text-xs text-base-content/45"
                        >
                            <span dir="ltr" class="technical-value">
                                {{ $peer['ip_address'] }}
                            </span>

                            <span>
                                آخرین اتصال: {{ $handshakeLabel }}
                            </span>

                            <span dir="ltr">
                                ↓ {{ number_format((int) $peer['received_bytes']) }} B
                                ·
                                ↑ {{ number_format((int) $peer['sent_bytes']) }} B
                            </span>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div
                class="flex flex-col items-center
                       px-5 py-10 text-center
                       sm:px-6"
            >
                <div
                    class="flex size-10 items-center justify-center
                           rounded-xl bg-base-200/70"
                >
                    <x-icon
                        name="lucide.smartphone"
                        class="size-4 text-base-content/40"
                    />
                </div>

                <p class="mt-3 text-sm font-medium text-base-content">
                    هنوز دستگاهی اضافه نشده است
                </p>

                <p class="mt-1 text-sm text-base-content/45">
                    اولین دستگاه را از فرم بالا ایجاد کنید.
                </p>
            </div>
        @endforelse
    </div>
</section>
