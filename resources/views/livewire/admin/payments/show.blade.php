<div class="space-y-5">
    <x-admin.page-header :title="'پرداخت #' . $payment->id" description="جزئیات read-only تراکنش و نتیجه تأیید درگاه." icon="lucide.credit-card">
        <x-slot:actions><x-button label="بازگشت به پرداخت‌ها" icon="lucide.arrow-right" :link="route('admin.payments.index')" wire:navigate class="btn-ghost btn-sm" /></x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <div class="flex items-center justify-between gap-3"><h2 class="text-sm font-semibold">تراکنش</h2><x-admin.status-badge :status="$payment->status" /></div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">درگاه</dt><dd class="mt-1 text-sm">{{ $payment->gateway }}</dd></div>
                <div><dt class="text-xs text-base-content/45">مبلغ</dt><dd class="mt-1 text-sm font-semibold">{{ number_format($payment->amount) }} {{ $payment->currency }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Gateway reference</dt><dd class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $payment->gateway_reference ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Transaction ID</dt><dd class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $payment->gateway_transaction_id ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Failure code</dt><dd class="mt-1 font-mono text-sm">{{ $payment->failure_code ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Verified at</dt><dd class="mt-1 text-sm">{{ $payment->verified_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <h2 class="text-sm font-semibold">ارتباط‌ها</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">سفارش</dt><dd class="mt-1"><a class="link link-hover font-mono text-sm" href="{{ route('admin.orders.show', $payment->order) }}" wire:navigate>#{{ $payment->order_id }}</a></dd></div>
                <div><dt class="text-xs text-base-content/45">کاربر</dt><dd class="mt-1"><a class="link link-hover text-sm" href="{{ route('admin.users.show', $payment->order->user) }}" wire:navigate>{{ $payment->order->user?->name ?: $payment->order->user?->phone }}</a></dd></div>
                <div><dt class="text-xs text-base-content/45">سرور</dt><dd class="mt-1 text-sm">@if($payment->order->historicalServer)<a class="link link-hover" href="{{ route('admin.servers.show', $payment->order->historicalServer) }}" wire:navigate>{{ $payment->order->historicalServer->name ?: $payment->order->historicalServer->host }}</a>@else—@endif</dd></div>
                <div><dt class="text-xs text-base-content/45">ایجاد</dt><dd class="mt-1 text-sm">{{ $payment->created_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </section>
    </div>
</div>
