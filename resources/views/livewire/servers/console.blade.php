<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    <div
        wire:init="loadConsole"
        class="space-y-4"
    >
        {{-- Console --}}
        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-base-300
                bg-base-100
            "
        >
            {{-- Header --}}
            <div
                class="
                    flex flex-col gap-3
                    border-b border-base-300
                    px-4 py-3.5
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    sm:px-5
                "
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="
                            flex size-9 shrink-0
                            items-center justify-center
                            rounded-xl
                            bg-base-200
                            text-base-content/55
                        "
                    >
                        <x-icon
                            name="lucide.monitor"
                            class="!size-4.5"
                        />
                    </div>

                    <div class="min-w-0">
                        <h2
                            class="
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            کنسول سرور
                        </h2>

                        <p
                            class="
                                mt-0.5
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            دسترسی مستقیم به کنسول VPS
                        </p>
                    </div>
                </div>

                <x-button
                    :label="$consoleUrl ? 'اتصال مجدد' : 'اتصال'"
                    icon="lucide.refresh-cw"
                    wire:click="loadConsole"
                    spinner="loadConsole"
                    class="
                        btn-outline btn-sm
                        self-start
                        rounded-xl
                        sm:self-auto
                    "
                />
            </div>

            {{-- Error --}}
            @if($consoleError !== null)
                <div
                    class="
                        flex
                        min-h-[52vh]
                        items-center justify-center
                        px-4 py-12
                        sm:px-5
                    "
                >
                    <div
                        class="
                            w-full max-w-lg
                            rounded-2xl
                            border border-error/15
                            bg-error/[0.04]
                            p-5
                        "
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="
                                    flex size-9 shrink-0
                                    items-center justify-center
                                    rounded-xl
                                    bg-error/10
                                    text-error
                                "
                            >
                                <x-icon
                                    name="lucide.circle-alert"
                                    class="!size-4.5"
                                />
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="
                                        text-sm font-medium
                                        text-error
                                    "
                                >
                                    اتصال به کنسول برقرار نشد
                                </p>

                                <p
                                    class="
                                        mt-1
                                        text-xs leading-6
                                        text-base-content/50
                                    "
                                >
                                    {{ $consoleError }}
                                </p>

                                <x-button
                                    label="تلاش مجدد"
                                    icon="lucide.refresh-cw"
                                    wire:click="loadConsole"
                                    spinner="loadConsole"
                                    class="
                                        btn-error
                                        btn-soft
                                        btn-sm
                                        mt-4
                                        rounded-xl
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Console ready --}}
            @elseif($consoleUrl !== null)
                <div
                    class="
                        relative
                        h-[60vh]
                        min-h-[460px]
                        max-h-[720px]
                        overflow-hidden
                        bg-neutral
                    "
                >
                    {{-- xDeploy overlay / hides provider toolbar --}}
                    <div
                        aria-hidden="true"
                        class="
                            absolute
                            inset-x-0 top-0 z-10
                            flex h-11
                            items-center
                            justify-between
                            border-b border-white/10
                            bg-neutral
                            px-4
                            text-neutral-content
                        "
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="
                                    size-1.5
                                    rounded-full
                                    bg-success
                                "
                            ></span>

                            <span
                                class="
                                    text-[10px]
                                    font-medium
                                    text-neutral-content/55
                                "
                            >
                                کنسول متصل
                            </span>
                        </div>

                        <div
                            dir="ltr"
                            class="
                                technical-value
                                text-[10px]
                                text-neutral-content/30
                            "
                        >
                            xDeploy Console
                        </div>
                    </div>

                    <iframe
                        wire:key="server-console-{{ md5($consoleUrl) }}"
                        src="{{ $consoleUrl }}"
                        title="کنسول سرور"
                        class="
                            block
                            h-full w-full
                            border-0
                            bg-neutral
                        "
                        referrerpolicy="no-referrer"
                        allowfullscreen
                    ></iframe>
                </div>

                {{-- Loading --}}
            @else
                <div
                    class="
                        flex
                        h-[60vh]
                        min-h-[460px]
                        max-h-[720px]
                        items-center justify-center
                        bg-neutral
                        px-5
                        text-center
                        text-neutral-content
                    "
                >
                    <div>
                        <span
                            class="
                                loading
                                loading-spinner
                                loading-md
                                opacity-60
                            "
                        ></span>

                        <p
                            class="
                                mt-3
                                text-xs
                                text-neutral-content/55
                            "
                        >
                            در حال برقراری اتصال به کنسول سرور...
                        </p>

                        <p
                            class="
                                mt-1
                                text-[10px]
                                text-neutral-content/30
                            "
                        >
                            این فرایند معمولاً چند ثانیه طول می‌کشد.
                        </p>
                    </div>
                </div>
            @endif
        </section>


        {{-- Console help --}}
        <section
            class="
                grid grid-cols-1
                gap-3
                lg:grid-cols-2
            "
        >
            <div
                role="alert"
                class="
                    alert
                    alert-info
                    alert-soft
                    items-start
                    rounded-2xl
                "
            >
                <x-icon
                    name="lucide.mouse-pointer-2"
                    class="mt-0.5 !size-4.5 shrink-0"
                />

                <div>
                    <p class="text-xs font-medium">
                        برای تایپ، ابتدا داخل کنسول کلیک کنید
                    </p>

                    <p class="mt-1 text-[11px] leading-5 opacity-65">
                        پس از فعال‌شدن فوکوس، ورودی صفحه‌کلید مستقیماً
                        به سرور ارسال می‌شود.
                    </p>
                </div>
            </div>

            <div
                role="alert"
                class="
                    alert
                    alert-info
                    alert-soft
                    items-start
                    rounded-2xl
                "
            >
                <x-icon
                    name="lucide.shield-check"
                    class="mt-0.5 !size-4.5 shrink-0"
                />

                <div>
                    <p class="text-xs font-medium">
                        کنسول مستقل از اتصال SSH است
                    </p>

                    <p class="mt-1 text-[11px] leading-5 opacity-65">
                        حتی اگر SSH سرور در دسترس نباشد، می‌توانید برای
                        بررسی و رفع مشکل از این کنسول استفاده کنید.
                    </p>
                </div>
            </div>

            <div
                role="alert"
                class="
                    alert
                    alert-warning
                    alert-soft
                    items-start
                    rounded-2xl
                    lg:col-span-2
                "
            >
                <x-icon
                    name="lucide.refresh-cw"
                    class="mt-0.5 !size-4.5 shrink-0"
                />

                <div>
                    <p class="text-xs font-medium">
                        اگر کنسول پاسخ نمی‌دهد، اتصال را تازه کنید
                    </p>

                    <p class="mt-1 text-[11px] leading-5 opacity-65">
                        از دکمه «اتصال مجدد» استفاده کنید تا یک نشست جدید
                        برای کنسول دریافت شود.
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-servers.workspace>
