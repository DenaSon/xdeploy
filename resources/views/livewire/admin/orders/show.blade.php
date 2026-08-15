<div class="space-y-5">
    <x-admin.page-header :title="'سفارش #' . $order->id" description="Snapshot تجاری سفارش، کاربر، سرور مرتبط و پرداخت‌ها." icon="lucide.receipt-text">
        <x-slot:actions><x-button label="بازگشت به سفارش‌ها" icon="lucide.arrow-right" :link="route('admin.orders.index')" wire:navigate class="btn-ghost btn-sm" /></x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <div class="flex items-center justify-between gap-3"><h2 class="text-sm font-semibold">سفارش</h2><x-admin.status-badge :status="$order->status" /></div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">کاربر</dt><dd class="mt-1 text-sm"><a class="link link-hover" href="{{ route('admin.users.show', $order->user) }}" wire:navigate>{{ $order->user?->name ?: $order->user?->phone }}</a></dd></div>
                <div><dt class="text-xs text-base-content/45">نوع</dt><dd class="mt-1 text-sm">{{ $order->isRenewal() ? 'تمدید' : 'خرید سرور' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">مبلغ نهایی</dt><dd class="mt-1 text-sm font-semibold">{{ number_format($order->final_amount) }} {{ $order->currency }}</dd></div>
                <div><dt class="text-xs text-base-content/45">هزینه Provider</dt><dd class="mt-1 text-sm">{{ number_format($order->provider_cost) }} {{ $order->currency }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Markup</dt><dd class="mt-1 text-sm">{{ $order->markup_percent }}%</dd></div>
                <div><dt class="text-xs text-base-content/45">Paid at</dt><dd class="mt-1 text-sm">{{ $order->paid_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <h2 class="text-sm font-semibold">Infrastructure snapshot</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">Region</dt><dd class="mt-1 text-sm">{{ $order->region_id }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Size</dt><dd class="mt-1 text-sm">{{ $order->size_id }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Image</dt><dd class="mt-1 text-sm">{{ $order->image_name }} {{ $order->image_version }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Disk</dt><dd class="mt-1 text-sm">{{ $order->selected_disk_gib }} GiB</dd></div>
                <div><dt class="text-xs text-base-content/45">Period</dt><dd class="mt-1 text-sm">{{ $order->period }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Server</dt><dd class="mt-1 text-sm">@if($order->historicalServer)<a class="link link-hover" href="{{ route('admin.servers.show', $order->historicalServer) }}" wire:navigate>{{ $order->historicalServer->name ?: $order->historicalServer->host }}</a>@else—@endif</dd></div>
            </dl>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-5"><h2 class="text-sm font-semibold">پرداخت‌های سفارش</h2></div>
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>پرداخت</th><th>درگاه</th><th>مبلغ</th><th>وضعیت</th><th>تأیید</th><th></th></tr></thead><tbody>
            @forelse($order->payments->sortByDesc('id') as $payment)
                <tr><td class="font-mono text-xs">#{{ $payment->id }}</td><td>{{ $payment->gateway }}</td><td>{{ number_format($payment->amount) }} {{ $payment->currency }}</td><td><x-admin.status-badge :status="$payment->status" /></td><td>{{ $payment->verified_at?->format('Y-m-d H:i') ?? '—' }}</td><td class="text-left"><x-button label="جزئیات" :link="route('admin.payments.show', $payment)" wire:navigate class="btn-ghost btn-xs" /></td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-sm text-base-content/45">پرداختی ثبت نشده است.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
