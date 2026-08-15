<div class="space-y-5">
    <x-admin.page-header
        :title="$server->name ?: 'سرور بدون نام'"
        description="اطلاعات مدیریتی سرور بدون نمایش یا تغییر credential اتصال."
        icon="lucide.server-cog"
    >
        <x-slot:actions><x-button label="بازگشت به سرورها" icon="lucide.arrow-right" :link="route('admin.servers.index')" wire:navigate class="btn-ghost btn-sm" /></x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <div class="flex items-center justify-between gap-3"><h2 class="text-sm font-semibold">اتصال و مالکیت</h2><x-admin.status-badge :status="$server->status" /></div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">شناسه</dt><dd class="mt-1 font-mono text-sm">#{{ $server->id }}</dd></div>
                <div><dt class="text-xs text-base-content/45">مالک</dt><dd class="mt-1 text-sm"><a class="link link-hover" href="{{ route('admin.users.show', $server->user) }}" wire:navigate>{{ $server->user?->name ?: $server->user?->phone }}</a></dd></div>
                <div><dt class="text-xs text-base-content/45">Host</dt><dd class="mt-1 font-mono text-sm" dir="ltr">{{ $server->host }}:{{ $server->port }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Username</dt><dd class="mt-1 font-mono text-sm" dir="ltr">{{ $server->username }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Authentication</dt><dd class="mt-1 text-sm">{{ $server->authentication_type->value }}</dd></div>
                <div><dt class="text-xs text-base-content/45">ایجاد</dt><dd class="mt-1 text-sm">{{ $server->created_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <h2 class="text-sm font-semibold">Cloud lifecycle</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-base-content/45">Provider</dt><dd class="mt-1 text-sm">{{ $server->cloud_provider ?: 'Manual' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Provider Server ID</dt><dd class="mt-1 font-mono text-sm">{{ $server->cloud_server_id ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Region</dt><dd class="mt-1 text-sm">{{ $server->cloud_region ?: '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Provisioned</dt><dd class="mt-1 text-sm">{{ $server->provisioned_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Expires</dt><dd class="mt-1 text-sm">{{ $server->expires_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-base-content/45">Terminated</dt><dd class="mt-1 text-sm">{{ $server->terminated_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-5"><h2 class="text-sm font-semibold">سفارش‌های مرتبط</h2></div>
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>سفارش</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr><td class="font-mono text-xs">#{{ $order->id }}</td><td>{{ $order->type->value }}</td><td>{{ number_format($order->final_amount) }} {{ $order->currency }}</td><td><x-admin.status-badge :status="$order->status" /></td><td class="text-left"><x-button label="جزئیات" :link="route('admin.orders.show', $order)" wire:navigate class="btn-ghost btn-xs" /></td></tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-sm text-base-content/45">سفارش مرتبطی وجود ندارد.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
