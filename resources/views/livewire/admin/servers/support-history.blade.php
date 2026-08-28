<div x-on:keydown.escape.window="$wire.closeSupportHistory()">
    <button
        type="button"
        wire:click="openSupportHistory"
        wire:loading.attr="disabled"
        wire:target="openSupportHistory"
        class="btn btn-ghost btn-sm"
    >
        <x-icon name="lucide.history" class="!size-4" />
        <span wire:loading.remove wire:target="openSupportHistory">سوابق پشتیبانی</span>
        <span wire:loading wire:target="openSupportHistory" class="loading loading-spinner loading-xs"></span>
    </button>

    @if ($supportHistoryOpen)
        <div
            class="modal modal-open"
            role="dialog"
            aria-modal="true"
            aria-labelledby="support-history-title-{{ $serverId }}"
        >
            <div class="modal-box max-w-5xl overflow-hidden p-0">
                <div class="flex items-start justify-between gap-4 border-b border-base-300 p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <x-icon name="lucide.history" class="!size-5 stroke-[1.7]" />
                        </span>
                        <div>
                            <h3 id="support-history-title-{{ $serverId }}" class="text-base font-semibold">
                                سوابق پشتیبانی سرور
                            </h3>
                            <p class="mt-1 text-xs leading-6 text-base-content/50">
                                آخرین ۵۰ عملیات ثبت‌شده برای این سرور
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSupportHistory"
                        class="btn btn-ghost btn-sm btn-square"
                        aria-label="بستن"
                    >
                        <x-icon name="lucide.x" class="!size-4" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto">
                    @forelse ($history as $entry)
                        <article class="border-b border-base-300 p-5 last:border-b-0 sm:p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold">{{ $entry->title }}</span>
                                        <span @class([
                                            'badge badge-sm',
                                            'badge-success' => $entry->successful,
                                            'badge-error' => ! $entry->successful,
                                        ])>
                                            {{ $entry->successful ? 'موفق' : 'ناموفق' }}
                                        </span>
                                    </div>

                                    @if (
                                        $entry->action === \App\Domain\Server\Enums\SupportAccessAction::ConnectionHostUpdated
                                        && isset($entry->metadata['old_host'], $entry->metadata['new_host'])
                                    )
                                        <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-base-200/60 px-3 py-2 font-mono text-xs" dir="ltr">
                                            <span>{{ $entry->metadata['old_host'] }}</span>
                                            <x-icon name="lucide.arrow-right" class="!size-3.5 text-base-content/35" />
                                            <span class="font-semibold text-primary">{{ $entry->metadata['new_host'] }}</span>
                                        </div>
                                    @endif

                                    <p class="mt-3 text-sm leading-7 text-base-content/65">
                                        {{ $entry->reason }}
                                    </p>
                                </div>

                                <dl class="grid shrink-0 gap-2 text-xs text-base-content/50 sm:grid-cols-2 lg:min-w-72 lg:grid-cols-1">
                                    <div class="flex items-center justify-between gap-4 rounded-lg bg-base-200/35 px-3 py-2">
                                        <dt>مدیر</dt>
                                        <dd class="font-medium text-base-content/70">{{ $entry->adminLabel }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4 rounded-lg bg-base-200/35 px-3 py-2">
                                        <dt>زمان</dt>
                                        <dd class="font-medium text-base-content/70">
                                            <x-persian-date :date="$entry->createdAt" />
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <x-icon name="lucide.history" class="mx-auto !size-8 text-base-content/20" />
                            <p class="mt-3 text-sm text-base-content/45">
                                هنوز سابقه پشتیبانی برای این سرور ثبت نشده است.
                            </p>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end border-t border-base-300 p-4 sm:px-6">
                    <button
                        type="button"
                        wire:click="closeSupportHistory"
                        class="btn btn-ghost btn-sm"
                    >
                        بستن
                    </button>
                </div>
            </div>

            <button
                type="button"
                wire:click="closeSupportHistory"
                class="modal-backdrop bg-black/40"
                aria-label="بستن سوابق پشتیبانی"
            ></button>
        </div>
    @endif
</div>
