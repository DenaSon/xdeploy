<div class="space-y-5">
    <x-admin.page-header title="پرداخت‌ها" description="مشاهده تراکنش‌های پرداخت و ارتباط هر پرداخت با سفارش و کاربر." icon="lucide.credit-card" />

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-3 border-b border-base-300 p-4 sm:grid-cols-3 sm:p-5">
            <x-input label="جست‌وجو" placeholder="شماره سفارش، مرجع یا کاربر" icon="lucide.search" wire:model.live.debounce.300ms="search" clearable />
            <label class="form-control"><span class="label-text mb-2 text-xs">وضعیت</span><select class="select select-bordered w-full" wire:model.live="status"><option value="all">همه وضعیت‌ها</option><option value="initiating">در حال آغاز</option><option value="pending">در انتظار</option><option value="paid">پرداخت‌شده</option><option value="failed">ناموفق</option><option value="cancelled">لغوشده</option></select></label>
            <label class="form-control"><span class="label-text mb-2 text-xs">درگاه</span><select class="select select-bordered w-full" wire:model.live="gateway"><option value="all">همه درگاه‌ها</option>@foreach($gateways as $gatewayName)<option value="{{ $gatewayName }}">{{ $gatewayName }}</option>@endforeach</select></label>
        </div>

        <div class="overflow-x-auto"><table class="table"><thead><tr><th>پرداخت</th><th>سفارش</th><th>کاربر</th><th>درگاه</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead><tbody>
            @forelse($payments as $payment)
                <tr wire:key="admin-payment-{{ $payment->id }}">
                    <td class="font-mono text-xs">#{{ $payment->id }}</td>
                    <td><a class="link link-hover font-mono text-xs" href="{{ route('admin.orders.show', $payment->order) }}" wire:navigate>#{{ $payment->order_id }}</a></td>
                    <td><a class="link link-hover text-sm" href="{{ route('admin.users.show', $payment->order->user) }}" wire:navigate>{{ $payment->order->user?->name ?: $payment->order->user?->phone }}</a></td>
                    <td>{{ $payment->gateway }}</td>
                    <td>{{ number_format($payment->amount) }} {{ $payment->currency }}</td>
                    <td><x-admin.status-badge :status="$payment->status" /></td>
                    <td class="text-left"><x-button label="جزئیات" icon="lucide.arrow-left" :link="route('admin.payments.show', $payment)" wire:navigate class="btn-ghost btn-sm" /></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-base-content/45">پرداختی پیدا نشد.</td></tr>
            @endforelse
        </tbody></table></div>

        @if($payments->hasPages())<div class="border-t border-base-300 p-4">{{ $payments->links() }}</div>@endif
    </section>
</div>
