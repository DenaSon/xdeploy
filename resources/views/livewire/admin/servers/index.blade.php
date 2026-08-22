<div class="space-y-5">
    <x-admin.page-header
        title="سرورها"
        description="مشاهده سرورهای متصل یا خریداری‌شده، مالک، وضعیت و اطلاعات Provider."
        icon="lucide.server"
    />

    @if($expirationMessage !== null)
        <div class="flex items-start gap-2 rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="mt-0.5 !size-4 shrink-0" />
            <span>{{ $expirationMessage }}</span>
        </div>
    @endif

    @if($expirationError !== null)
        <div class="flex items-start gap-2 rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            <x-icon name="lucide.circle-alert" class="mt-0.5 !size-4 shrink-0" />
            <span>{{ $expirationError }}</span>
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-3 border-b border-base-300 p-4 sm:grid-cols-3 sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="نام، IP یا کاربر"
                icon="lucide.search"
                wire:model.live.debounce.300ms="search"
                clearable
            />

            <label class="form-control">
                <span class="label-text mb-2 text-xs">وضعیت</span>
                <select class="select select-bordered w-full" wire:model.live="status">
                    <option value="all">همه وضعیت‌ها</option>
                    <option value="active">فعال</option>
                    <option value="inactive">غیرفعال</option>
                </select>
            </label>

            <label class="form-control">
                <span class="label-text mb-2 text-xs">منبع</span>
                <select class="select select-bordered w-full" wire:model.live="source">
                    <option value="all">همه سرورها</option>
                    <option value="cloud">خریداری‌شده</option>
                    <option value="manual">متصل‌شده توسط کاربر</option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>سرور</th><th>مالک</th><th>منبع</th><th>وضعیت</th><th>انقضا</th><th></th></tr></thead>
                <tbody>
                    @forelse($servers as $server)
                        <tr wire:key="admin-server-{{ $server->id }}">
                            <td><div class="font-medium">{{ $server->name ?: 'بدون نام' }}</div><div class="mt-1 font-mono text-xs text-base-content/45" dir="ltr">{{ $server->host }}:{{ $server->port }}</div></td>
                            <td><a class="link link-hover text-sm" href="{{ route('admin.users.show', $server->user) }}" wire:navigate>{{ $server->user?->name ?: $server->user?->phone }}</a></td>
                            <td><span class="badge badge-ghost badge-sm">{{ $server->isCloudProvisioned() ? ($server->cloud_provider ?: 'Cloud') : 'Manual' }}</span></td>
                            <td><x-admin.status-badge :status="$server->status" /></td>
                            <td class="text-sm text-base-content/60">{{ $server->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-left">
                                <div class="flex items-center justify-end gap-1">
                                    @if($server->isCloudProvisioned())
                                        @if($server->isTerminated() || $server->termination_started_at !== null)
                                            <span class="badge badge-ghost badge-sm">در حال حذف</span>
                                        @elseif($server->hasExpired())
                                            <span class="badge badge-warning badge-sm">منقضی</span>
                                        @else
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-sm text-error"
                                                wire:loading.attr="disabled"
                                                wire:target="expireNow"
                                                x-on:click="if (confirm('تاریخ انقضای این سرور روی همین لحظه قرار می‌گیرد. حذف در اجرای بعدی Scheduler انجام می‌شود. ادامه می‌دهید؟')) $wire.expireNow({{ $server->id }})"
                                            >
                                                <x-icon name="lucide.calendar-x" class="!size-4" />
                                                منقضی کردن
                                            </button>
                                        @endif
                                    @endif

                                    <x-button
                                        label="جزئیات"
                                        icon="lucide.arrow-left"
                                        :link="route('admin.servers.show', $server)"
                                        wire:navigate
                                        class="btn-ghost btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-sm text-base-content/45">سروری پیدا نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($servers->hasPages())
            <div class="border-t border-base-300 p-4">{{ $servers->links() }}</div>
        @endif
    </section>
</div>
