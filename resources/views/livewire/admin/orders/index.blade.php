<div class="space-y-5">
    <x-admin.page-header title="سفارش‌ها" description="مشاهده سفارش‌های خرید و تمدید و وضعیت چرخه پرداخت و fulfillment." icon="lucide.receipt-text" />

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-3 border-b border-base-300 p-4 sm:grid-cols-3 sm:p-5">
            <x-input label="جست‌وجو" placeholder="شماره سفارش، کاربر یا سرور" icon="lucide.search" wire:model.live.debounce.300ms="search" clearable />
            <label class="form-control"><span class="label-text mb-2 text-xs">وضعیت</span><select class="select select-bordered w-full" wire:model.live="status"><option value="all">همه وضعیت‌ها</option><option value="pending_payment">در انتظار پرداخت</option><option value="paid">پرداخت‌شده</option><option value="provisioning">در حال آماده‌سازی</option><option value="fulfilled">تکمیل‌شده</option><option value="failed">ناموفق</option><option value="cancelled">لغوشده</option><option value="expired">منقضی</option></select></label>
            <label class="form-control"><span class="label-text mb-2 text-xs">نوع</span><select class="select select-bordered w-full" wire:model.live="type"><option value="all">همه انواع</option><option value="provisioning">خرید سرور</option><option value="renewal">تمدید</option></select></label>
        </div>

        <div class="overflow-x-auto"><table class="table"><thead><tr><th>سفارش</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>پرداخت‌ها</th><th>وضعیت</th><th></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr wire:key="admin-order-{{ $order->id }}">
                    <td class="font-mono text-xs">#{{ $order->id }}</td>
                    <td><a class="link link-hover text-sm" href="{{ route('admin.users.show', $order->user) }}" wire:navigate>{{ $order->user?->name ?: $order->user?->phone }}</a></td>
                    <td>{{ $order->isRenewal() ? 'تمدید' : 'خرید سرور' }}</td>
                    <td>{{ number_format($order->final_amount) }} {{ $order->currency }}</td>
                    <td>{{ number_format($order->payments_count) }}</td>
                    <td><x-admin.status-badge :status="$order->status" /></td>
                    <td class="text-left"><x-button label="جزئیات" icon="lucide.arrow-left" :link="route('admin.orders.show', $order)" wire:navigate class="btn-ghost btn-sm" /></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-base-content/45">سفارشی پیدا نشد.</td></tr>
            @endforelse
        </tbody></table></div>

        @if($orders->hasPages())<div class="border-t border-base-300 p-4">{{ $orders->links() }}</div>@endif
    </section>
</div>
