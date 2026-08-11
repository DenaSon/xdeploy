<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    <div
        wire:init="loadConsole"
        class="space-y-4"
    >
        <section
            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
        >
            <div
                class="flex flex-col gap-3 border-b border-base-300 px-4 py-3.5
                       sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center
                               rounded-xl bg-base-200 text-base-content/55"
                    >
                        <x-icon
                            name="lucide.monitor"
                            class="!size-4.5"
                        />
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-base-content">
                            کنسول سرور
                        </h2>

                        <p class="mt-0.5 text-[11px] text-base-content/40">
                            دسترسی مستقیم به کنسول VPS
                        </p>
                    </div>
                </div>

                <x-button
                    :label="$consoleUrl ? 'اتصال مجدد' : 'اتصال'"
                    icon="lucide.refresh-cw"
                    wire:click="loadConsole"
                    spinner="loadConsole"
                    class="btn-outline btn-sm rounded-xl"
                />
            </div>

            @if($consoleError !== null)
                <div
                    class="m-4 rounded-xl border border-error/15 bg-error/[0.04]
                           px-4 py-3 sm:m-5"
                >
                    <div class="flex items-start gap-2.5">
                        <x-icon
                            name="lucide.circle-alert"
                            class="mt-0.5 !size-4 shrink-0 text-error"
                        />

                        <div class="min-w-0">
                            <p class="text-xs font-medium text-error">
                                {{ $consoleError }}
                            </p>

                            <p class="mt-1 text-[11px] leading-5 text-base-content/45">
                                چند لحظه دیگر دوباره تلاش کنید.
                            </p>
                        </div>
                    </div>
                </div>
            @elseif($consoleUrl !== null)
                <iframe
                    wire:key="server-console-{{ md5($consoleUrl) }}"
                    src="{{ $consoleUrl }}"
                    title="کنسول سرور"
                    class="h-[70vh] w-full border-0 bg-neutral"
                    referrerpolicy="no-referrer"
                    allowfullscreen
                ></iframe>
            @else
                <div
                    class="flex h-[70vh] min-h-72 items-center justify-center
                           bg-neutral px-5 text-center text-neutral-content"
                >
                    <div>
                        <span class="loading loading-spinner loading-md"></span>

                        <p class="mt-3 text-xs text-neutral-content/60">
                            در حال برقراری اتصال به کنسول سرور...
                        </p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-servers.workspace>
