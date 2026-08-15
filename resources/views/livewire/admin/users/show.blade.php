<div class="space-y-5">
    <x-admin.page-header
        :title="$user->name ?: 'کاربر بدون نام'"
        description="نمای عملیاتی کاربر و ارتباط او با سرورها و سفارش‌ها."
        icon="lucide.user-round"
    >
        <x-slot:actions>
            <x-button
                label="بازگشت به کاربران"
                icon="lucide.arrow-right"
                :link="route('admin.users.index')"
                wire:navigate
                class="btn-ghost btn-sm"
            />
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">اطلاعات حساب</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">شناسه</dt><dd class="mt-1 font-mono text-sm">#{{ $user->id }}</dd></div>
                <div><dt class="text-xs text-base-content/45">شماره موبایل</dt><dd class="mt-1 font-mono text-sm" dir="ltr">{{ $user->phone }}</dd></div>
                <div><dt class="text-xs text-base-content/45">نام</dt><dd class="mt-1 text-sm">{{ $user->name ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">تاریخ عضویت</dt><dd class="mt-1 text-sm">{{ $user->created_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <div class="text-xs text-base-content/45">تعداد سرورها</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($user->servers_count) }}</div>
            @if($user->isAdmin())
                <div class="mt-4"><span class="badge badge-primary badge-sm">مدیر سیستم</span></div>
            @endif
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-5"><h2 class="text-sm font-semibold">آخرین سرورها</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>سرور</th><th>آدرس</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                    @forelse($servers as $server)
                        <tr>
                            <td>{{ $server->name ?: 'بدون نام' }}</td>
                            <td class="font-mono text-xs" dir="ltr">{{ $server->host }}:{{ $server->port }}</td>
                            <td><x-admin.status-badge :status="$server->status" /></td>
                            <td class="text-left"><x-button label="جزئیات" :link="route('admin.servers.show', $server)" wire:navigate class="btn-ghost btn-xs" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-sm text-base-content/45">سروری ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-5"><h2 class="text-sm font-semibold">آخرین سفارش‌ها</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>سفارش</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="font-mono text-xs">#{{ $order->id }}</td>
                            <td>{{ number_format($order->final_amount) }} {{ $order->currency }}</td>
                            <td><x-admin.status-badge :status="$order->status" /></td>
                            <td class="text-left"><x-button label="جزئیات" :link="route('admin.orders.show', $order)" wire:navigate class="btn-ghost btn-xs" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-sm text-base-content/45">سفارشی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
