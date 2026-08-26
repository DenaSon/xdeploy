@props(['server'])

<div class="grid gap-4 lg:grid-cols-2">
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex items-center justify-between gap-3 border-b border-base-300 px-5 py-4">
            <div class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-icon name="lucide.network" class="!size-4.5 stroke-[1.7]" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold">اتصال و مالکیت</h2>
                    <p class="mt-0.5 text-xs text-base-content/45">اطلاعات اصلی دسترسی و مالک سرور</p>
                </div>
            </div>

            <x-admin.status-badge :status="$server->status" />
        </div>

        <dl class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">شناسه سرور</dt>
                <dd class="mt-1.5 font-mono text-sm">#{{ $server->id }}</dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">مالک</dt>
                <dd class="mt-1.5 text-sm font-medium">
                    <a
                        class="link link-hover"
                        href="{{ route('admin.users.show', $server->user) }}"
                        wire:navigate
                    >
                        {{ $server->user?->name ?: $server->user?->phone }}
                    </a>
                </dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">آدرس اتصال</dt>
                <dd class="mt-1.5 truncate font-mono text-sm" dir="ltr">
                    {{ $server->host ?: '—' }}{{ $server->host ? ':'.$server->port : '' }}
                </dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">نام کاربری</dt>
                <dd class="mt-1.5 truncate font-mono text-sm" dir="ltr">
                    {{ $server->username ?: '—' }}
                </dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">نوع احراز هویت</dt>
                <dd class="mt-1.5 text-sm">{{ $server->authentication_type->label() }}</dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">تاریخ ایجاد</dt>
                <dd class="mt-1.5 text-sm font-medium">
                    <x-persian-date :date="$server->created_at" />
                </dd>
            </div>
        </dl>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex items-center gap-2.5 border-b border-base-300 px-5 py-4">
            <span class="flex size-9 items-center justify-center rounded-xl bg-info/10 text-info">
                <x-icon name="lucide.cloud-cog" class="!size-4.5 stroke-[1.7]" />
            </span>
            <div>
                <h2 class="text-sm font-semibold">زیرساخت و چرخه عمر</h2>
                <p class="mt-0.5 text-xs text-base-content/45">وضعیت سرور در ارائه‌دهنده و زمان‌بندی سرویس</p>
            </div>
        </div>

        <dl class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">ارائه‌دهنده</dt>
                <dd class="mt-1.5 text-sm font-medium">{{ $server->cloud_provider ?: 'اتصال دستی' }}</dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">شناسه سرور نزد ارائه‌دهنده</dt>
                <dd class="mt-1.5 truncate font-mono text-sm" dir="ltr">{{ $server->cloud_server_id ?: '—' }}</dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">ناحیه</dt>
                <dd class="mt-1.5 text-sm">{{ $server->cloud_region ?: '—' }}</dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">زمان آماده‌سازی</dt>
                <dd class="mt-1.5 text-sm font-medium">
                    <x-persian-date :date="$server->provisioned_at" />
                </dd>
            </div>

            <div class="rounded-xl border border-primary/10 bg-primary/[0.035] px-4 py-3.5">
                <dt class="text-xs text-base-content/45">تاریخ انقضا</dt>
                <dd class="mt-1.5 text-sm font-semibold">
                    <x-persian-date :date="$server->expires_at" />
                </dd>
            </div>

            <div class="rounded-xl bg-base-200/45 px-4 py-3.5">
                <dt class="text-xs text-base-content/45">زمان خاتمه</dt>
                <dd class="mt-1.5 text-sm font-medium">
                    <x-persian-date :date="$server->terminated_at" />
                </dd>
            </div>
        </dl>
    </section>
</div>
