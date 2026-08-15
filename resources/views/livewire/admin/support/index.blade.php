@php
    $openCount = (int) ($statusCounts['open'] ?? 0);
    $answeredCount = (int) ($statusCounts['answered'] ?? 0);
    $closedCount = (int) ($statusCounts['closed'] ?? 0);
@endphp

<div class="space-y-5">
    <x-admin.page-header
        title="پشتیبانی"
        description="مدیریت درخواست‌های کاربران، مشاهده زمینه حساب و سرور، و ادامه گفتگو از یک مسیر متمرکز."
        icon="lucide.headset"
    />

    <section class="grid gap-2.5 sm:grid-cols-3">
        <div class="rounded-2xl bg-base-100 px-4 py-3.5 ring-1 ring-base-300/70">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-base-content/45">نیازمند پاسخ</span>
                <span class="size-2 rounded-full bg-warning"></span>
            </div>
            <div class="mt-2 text-lg font-semibold text-base-content">{{ number_format($openCount) }}</div>
        </div>

        <div class="rounded-2xl bg-base-100 px-4 py-3.5 ring-1 ring-base-300/70">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-base-content/45">پاسخ داده‌شده</span>
                <span class="size-2 rounded-full bg-primary"></span>
            </div>
            <div class="mt-2 text-lg font-semibold text-base-content">{{ number_format($answeredCount) }}</div>
        </div>

        <div class="rounded-2xl bg-base-100 px-4 py-3.5 ring-1 ring-base-300/70">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-base-content/45">بسته‌شده</span>
                <span class="size-2 rounded-full bg-base-content/25"></span>
            </div>
            <div class="mt-2 text-lg font-semibold text-base-content">{{ number_format($closedCount) }}</div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="space-y-3 border-b border-base-300 p-4 sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="شماره درخواست، موضوع، نام، موبایل، سرور یا IP"
                icon="lucide.search"
                wire:model.live.debounce.300ms="search"
                clearable
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex items-center gap-1 rounded-xl bg-base-200/60 p-1">
                    @foreach([
                        'open' => 'باز',
                        'answered' => 'پاسخ داده‌شده',
                        'closed' => 'بسته',
                        'all' => 'همه',
                    ] as $value => $label)
                        <button
                            type="button"
                            wire:click="setFilter('{{ $value }}')"
                            wire:loading.attr="disabled"
                            wire:target="setFilter"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition-all duration-150',
                                'bg-base-100 text-base-content shadow-sm shadow-base-content/[0.03]' => $filter === $value,
                                'text-base-content/45 hover:text-base-content/70' => $filter !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div wire:loading wire:target="setFilter,search" class="items-center gap-2 text-[10px] text-base-content/35">
                    <span class="loading loading-spinner loading-xs text-primary"></span>
                    در حال به‌روزرسانی
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>درخواست</th>
                        <th>کاربر</th>
                        <th>دسته</th>
                        <th>وضعیت</th>
                        <th>آخرین فعالیت</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($requests as $supportRequest)
                        <tr wire:key="admin-support-request-{{ $supportRequest->id }}" class="align-middle">
                            <td class="min-w-64">
                                <div class="font-medium text-base-content">{{ $supportRequest->subject }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-base-content/40">
                                    <span class="font-mono" dir="ltr">#SUP-{{ str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT) }}</span>
                                    <span>•</span>
                                    <span>{{ number_format($supportRequest->messages_count) }} پیام</span>
                                    @if($supportRequest->server)
                                        <span>•</span>
                                        <span>{{ $supportRequest->server->name ?: $supportRequest->server->host }}</span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="text-sm font-medium text-base-content/75">
                                    {{ $supportRequest->user->displayName() ?? 'بدون نام' }}
                                </div>
                                <div class="mt-1 font-mono text-[10px] text-base-content/40" dir="ltr">
                                    {{ $supportRequest->user->phone }}
                                </div>
                            </td>

                            <td><x-support.category-badge :category="$supportRequest->category" /></td>
                            <td><x-support.status-badge :status="$supportRequest->status" /></td>

                            <td class="whitespace-nowrap text-xs text-base-content/50" dir="ltr">
                                {{ $supportRequest->last_message_at?->format('Y-m-d H:i') }}
                            </td>

                            <td class="text-left">
                                <x-button
                                    label="مشاهده"
                                    icon="lucide.arrow-left"
                                    :link="route('admin.support.show', ['supportRequestId' => $supportRequest->id])"
                                    wire:navigate
                                    class="btn-ghost btn-sm rounded-xl"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <span class="mx-auto flex size-10 items-center justify-center rounded-xl bg-base-200/70 text-base-content/30">
                                    <x-icon name="lucide.inbox" class="!size-4.5" />
                                </span>
                                <div class="mt-3 text-sm font-medium text-base-content/60">درخواستی پیدا نشد.</div>
                                <div class="mt-1 text-xs text-base-content/35">فیلتر یا عبارت جست‌وجو را تغییر دهید.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="border-t border-base-300 p-4">
                {{ $requests->links() }}
            </div>
        @endif
    </section>
</div>
