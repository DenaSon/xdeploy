@php
    $openCount = (int) ($statusCounts['open'] ?? 0);
    $answeredCount = (int) ($statusCounts['answered'] ?? 0);
    $closedCount = (int) ($statusCounts['closed'] ?? 0);
    $totalCount = $openCount + $answeredCount + $closedCount;
@endphp

<div dir="rtl" class="mx-auto w-full max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex min-w-0 items-start gap-3.5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10">
                <x-icon name="lucide.headset" class="!size-[18px] stroke-[1.8]" />
            </span>

            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight text-base-content sm:text-2xl">
                    پشتیبانی
                </h1>

                <p class="mt-1 max-w-xl text-xs leading-6 text-base-content/45 sm:text-sm">
                    درخواست‌های پشتیبانی خود را ثبت کنید و ادامه گفتگو با تیم {{ config('app.name') }} را از همین‌جا پیگیری کنید.
                </p>
            </div>
        </div>

        <x-button
            label="درخواست جدید"
            icon="lucide.plus"
            :link="route('panel.support.create')"
            wire:navigate
            class="btn-primary btn-sm self-start rounded-xl px-4 sm:self-auto"
        />
    </header>

    <section class="grid gap-2.5 sm:grid-cols-3" aria-label="خلاصه وضعیت درخواست‌ها">
        <div class="rounded-2xl bg-base-100 px-4 py-3.5 ring-1 ring-base-300/70">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-base-content/45">نیازمند بررسی</span>
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
                <span class="text-xs text-base-content/45">کل درخواست‌ها</span>
                <x-icon name="lucide.messages-square" class="!size-4 text-base-content/30" />
            </div>
            <div class="mt-2 text-lg font-semibold text-base-content">{{ number_format($totalCount) }}</div>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 border-y border-base-300/60 py-3">
        <div class="inline-flex items-center gap-1 rounded-xl bg-base-200/60 p-1">
            @foreach([
                'all' => 'همه',
                'open' => 'باز',
                'answered' => 'پاسخ داده‌شده',
                'closed' => 'بسته',
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

        <div wire:loading wire:target="setFilter" class="items-center gap-2 text-[10px] text-base-content/35">
            <span class="loading loading-spinner loading-xs text-primary"></span>
            در حال به‌روزرسانی
        </div>
    </div>

    @if($requests->isEmpty())
        <section class="relative overflow-hidden rounded-2xl border border-base-300/80 bg-base-100 px-5 py-14 text-center sm:px-8 sm:py-16">
            <div aria-hidden="true" class="pointer-events-none absolute start-1/2 top-8 size-56 -translate-x-1/2 rounded-full bg-primary/[0.04] blur-3xl"></div>

            <div class="relative mx-auto max-w-md">
                <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-base-200/70 text-base-content/35">
                    <x-icon name="lucide.messages-square" class="!size-5 stroke-[1.7]" />
                </span>

                <h2 class="mt-4 text-sm font-semibold text-base-content">
                    {{ $filter === 'all' ? 'هنوز درخواستی ثبت نکرده‌اید' : 'درخواستی با این وضعیت وجود ندارد' }}
                </h2>

                <p class="mx-auto mt-1.5 max-w-sm text-xs leading-6 text-base-content/45">
                    اگر برای استفاده از سرویس‌ها، پرداخت یا حساب کاربری به کمک نیاز دارید، یک درخواست جدید ثبت کنید.
                </p>

                @if($filter === 'all')
                    <x-button
                        label="ثبت اولین درخواست"
                        icon="lucide.plus"
                        :link="route('panel.support.create')"
                        wire:navigate
                        class="btn-primary btn-sm mt-5 rounded-xl"
                    />
                @endif
            </div>
        </section>
    @else
        <section class="space-y-2.5" aria-label="فهرست درخواست‌های پشتیبانی">
            @foreach($requests as $supportRequest)
                <a
                    href="{{ route('panel.support.show', ['supportRequestId' => $supportRequest->id]) }}"
                    wire:navigate
                    wire:key="support-request-{{ $supportRequest->id }}"
                    class="group block rounded-2xl border border-base-300/80 bg-base-100 p-4 transition duration-150 hover:border-primary/20 hover:bg-primary/[0.015] sm:p-5"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[10px] text-base-content/35" dir="ltr">
                                    #SUP-{{ str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                                <x-support.status-badge :status="$supportRequest->status" />
                                <x-support.category-badge :category="$supportRequest->category" />
                            </div>

                            <h2 class="mt-2.5 truncate text-sm font-semibold text-base-content transition-colors group-hover:text-primary sm:text-base">
                                {{ $supportRequest->subject }}
                            </h2>

                            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-base-content/40">
                                @if($supportRequest->server)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="lucide.server" class="!size-3.5" />
                                        {{ $supportRequest->server->name ?: $supportRequest->server->host }}
                                    </span>
                                @endif

                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide.message-circle" class="!size-3.5" />
                                    {{ number_format($supportRequest->messages_count) }} پیام
                                </span>

                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide.clock-3" class="!size-3.5" />
                                    {{ $supportRequest->last_message_at?->format('Y-m-d H:i') }}
                                </span>
                            </div>
                        </div>

                        <span class="flex size-8 shrink-0 items-center justify-center self-end rounded-xl bg-base-200/65 text-base-content/35 transition group-hover:bg-primary/10 group-hover:text-primary sm:self-center">
                            <x-icon name="lucide.arrow-left" class="!size-4" />
                        </span>
                    </div>
                </a>
            @endforeach
        </section>

        @if($requests->hasPages())
            <div class="pt-2">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
</div>
