<div class="space-y-5">
    <x-admin.page-header
        title="نمای کلی مدیریت"
        description="مرور وضعیت کاربران، سرورها، سفارش‌ها و عملکرد مالی {{ config('app.name') }}."
        icon="lucide.layout-dashboard"
    />

    {{-- Primary KPIs --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-base-content/45">کل کاربران</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ number_format($totalUsers) }}</p>
                </div>

                <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-icon name="lucide.users" class="!size-4 stroke-[1.8]" />
                </span>
            </div>

            <a
                href="{{ route('admin.users.index') }}"
                wire:navigate
                class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
                مشاهده کاربران
                <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
            </a>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-base-content/45">سرورهای فعال</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ number_format($activeServers) }}</p>
                    <p class="mt-1 text-[11px] text-base-content/40">از {{ number_format($totalServers) }} سرور ثبت‌شده</p>
                </div>

                <span class="flex size-9 items-center justify-center rounded-xl bg-success/10 text-success">
                    <x-icon name="lucide.server" class="!size-4 stroke-[1.8]" />
                </span>
            </div>

            <a
                href="{{ route('admin.servers.index') }}"
                wire:navigate
                class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
                مشاهده سرورها
                <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
            </a>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-base-content/45">کل سفارش‌ها</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ number_format($totalOrders) }}</p>
                    <p class="mt-1 text-[11px] text-base-content/40">
                        {{ number_format($financial['paidOrdersCount']) }} سفارش با پرداخت تأییدشده
                    </p>
                </div>

                <span class="flex size-9 items-center justify-center rounded-xl bg-info/10 text-info">
                    <x-icon name="lucide.receipt-text" class="!size-4 stroke-[1.8]" />
                </span>
            </div>

            <a
                href="{{ route('admin.orders.index') }}"
                wire:navigate
                class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
                مشاهده سفارش‌ها
                <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
            </a>
        </section>

        <section class="rounded-2xl border border-success/25 bg-success/[0.035] p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-base-content/45">سود از Markup</p>
                    <p class="mt-2 truncate text-2xl font-semibold tracking-tight text-success">
                        {{ number_format($financial['markupProfit']) }}
                    </p>
                    <p class="mt-1 text-[11px] text-base-content/40">ریال · پرداخت‌های تأییدشده</p>
                </div>

                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                    <x-icon name="lucide.trending-up" class="!size-4 stroke-[1.8]" />
                </span>
            </div>
        </section>
    </div>

    {{-- Accounting --}}
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex flex-col gap-3 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                    <x-icon name="lucide.wallet-cards" class="!size-4 stroke-[1.8]" />
                </span>

                <div>
                    <h2 class="text-sm font-semibold">خلاصه حسابداری</h2>
                    <p class="mt-1 text-xs text-base-content/45">بر اساس سفارش‌هایی که پرداخت آن‌ها تأیید شده است.</p>
                </div>
            </div>

            <span class="badge badge-outline badge-sm gap-1.5 self-start sm:self-auto">
                Markup میانگین
                <strong>{{ number_format($financial['averageMarkupPercent'], 1) }}٪</strong>
            </span>
        </div>

        <div class="grid gap-px bg-base-300 sm:grid-cols-3">
            <div class="bg-base-100 p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-base-content/45">
                    <x-icon name="lucide.badge-dollar-sign" class="!size-3.5 stroke-[1.8]" />
                    فروش تأییدشده
                </div>
                <div class="mt-2 text-xl font-semibold">{{ number_format($financial['grossSales']) }}</div>
                <div class="mt-1 text-[11px] text-base-content/40">ریال</div>
            </div>

            <div class="bg-base-100 p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-base-content/45">
                    <x-icon name="lucide.cloud" class="!size-3.5 stroke-[1.8]" />
                    هزینه زیرساخت
                </div>
                <div class="mt-2 text-xl font-semibold">{{ number_format($financial['providerCost']) }}</div>
                <div class="mt-1 text-[11px] text-base-content/40">ریال · هزینه Provider</div>
            </div>

            <div class="bg-base-100 p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-success">
                    <x-icon name="lucide.chart-no-axes-combined" class="!size-3.5 stroke-[1.8]" />
                    سود از Markup
                </div>
                <div class="mt-2 text-xl font-semibold text-success">{{ number_format($financial['markupProfit']) }}</div>
                <div class="mt-1 text-[11px] text-base-content/40">ریال</div>
            </div>
        </div>

        <div class="flex items-start gap-2 border-t border-base-300 bg-base-200/30 px-4 py-3 text-[11px] leading-6 text-base-content/45 sm:px-5">
            <x-icon name="lucide.info" class="mt-1 !size-3.5 shrink-0 stroke-[1.8]" />
            <p>
                سود از Markup برابر با مبلغ نهایی فروش منهای هزینه Provider است. کارمزد درگاه، مالیات و هزینه‌های عملیاتی در این عدد لحاظ نشده‌اند.
            </p>
        </div>
    </section>

    {{-- Recent orders --}}
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex items-center justify-between gap-3 border-b border-base-300 p-4 sm:p-5">
            <div>
                <h2 class="text-sm font-semibold">۱۰ سفارش آخر</h2>
                <p class="mt-1 text-xs text-base-content/45">جدیدترین سفارش‌های ثبت‌شده در سیستم.</p>
            </div>

            <a href="{{ route('admin.orders.index') }}" wire:navigate class="btn btn-ghost btn-sm">
                همه سفارش‌ها
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>سفارش</th>
                        <th>کاربر</th>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr wire:key="dashboard-order-{{ $order->id }}">
                            <td class="font-mono text-xs">#{{ $order->id }}</td>
                            <td>
                                @if($order->user)
                                    <a href="{{ route('admin.users.show', $order->user) }}" wire:navigate class="link link-hover text-sm">
                                        {{ $order->user->name ?: $order->user->phone }}
                                    </a>
                                @else
                                    <span class="text-sm text-base-content/40">—</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $order->type->value === 'renewal' ? 'تمدید' : 'خرید سرور' }}</td>
                            <td class="whitespace-nowrap text-sm">{{ number_format($order->final_amount) }} {{ $order->currency }}</td>
                            <td><x-admin.status-badge :status="$order->status" /></td>
                            <td class="whitespace-nowrap text-xs text-base-content/50">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-left">
                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="btn btn-square btn-ghost btn-xs" aria-label="مشاهده سفارش">
                                    <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-sm text-base-content/40">هنوز سفارشی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 2xl:grid-cols-2">
        {{-- Recent users --}}
        <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
            <div class="flex items-center justify-between gap-3 border-b border-base-300 p-4 sm:p-5">
                <div>
                    <h2 class="text-sm font-semibold">۱۰ کاربر جدید</h2>
                    <p class="mt-1 text-xs text-base-content/45">آخرین حساب‌های ایجادشده.</p>
                </div>

                <a href="{{ route('admin.users.index') }}" wire:navigate class="btn btn-ghost btn-sm">همه کاربران</a>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>سرور</th>
                            <th>سفارش</th>
                            <th>عضویت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers as $user)
                            <tr wire:key="dashboard-user-{{ $user->id }}">
                                <td>
                                    <div class="text-sm font-medium">{{ $user->name ?: 'بدون نام' }}</div>
                                    <div class="mt-0.5 text-xs text-base-content/45" dir="ltr">{{ $user->phone }}</div>
                                </td>
                                <td>{{ number_format($user->servers_count) }}</td>
                                <td>{{ number_format($user->orders_count) }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/50">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-left">
                                    <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="btn btn-square btn-ghost btn-xs" aria-label="مشاهده کاربر">
                                        <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-base-content/40">هنوز کاربری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Recent servers --}}
        <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
            <div class="flex items-center justify-between gap-3 border-b border-base-300 p-4 sm:p-5">
                <div>
                    <h2 class="text-sm font-semibold">۱۰ سرور جدید</h2>
                    <p class="mt-1 text-xs text-base-content/45">جدیدترین سرورهای متصل یا خریداری‌شده.</p>
                </div>

                <a href="{{ route('admin.servers.index') }}" wire:navigate class="btn btn-ghost btn-sm">همه سرورها</a>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>سرور</th>
                            <th>مالک</th>
                            <th>منبع</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentServers as $server)
                            <tr wire:key="dashboard-server-{{ $server->id }}">
                                <td>
                                    <div class="text-sm font-medium">{{ $server->name ?: 'بدون نام' }}</div>
                                    <div class="mt-0.5 font-mono text-[11px] text-base-content/40" dir="ltr">{{ $server->host }}</div>
                                </td>
                                <td>
                                    @if($server->user)
                                        <a href="{{ route('admin.users.show', $server->user) }}" wire:navigate class="link link-hover text-sm">
                                            {{ $server->user->name ?: $server->user->phone }}
                                        </a>
                                    @else
                                        <span class="text-sm text-base-content/40">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-ghost badge-sm">
                                        {{ $server->isCloudProvisioned() ? ($server->cloud_provider ?: 'Cloud') : 'Manual' }}
                                    </span>
                                </td>
                                <td><x-admin.status-badge :status="$server->status" /></td>
                                <td class="whitespace-nowrap text-xs text-base-content/50">{{ $server->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-left">
                                    <a href="{{ route('admin.servers.show', ['adminServer' => $server]) }}" wire:navigate class="btn btn-square btn-ghost btn-xs" aria-label="مشاهده سرور">
                                        <x-icon name="lucide.arrow-left" class="!size-3.5 stroke-[1.8]" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm text-base-content/40">هنوز سروری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
