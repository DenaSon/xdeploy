<div class="space-y-5">
    <x-admin.page-header
        title="کاربران"
        description="مشاهده کاربران ثبت‌شده و دسترسی سریع به سرورها و سابقه سفارش‌های هر کاربر."
        icon="lucide.users"
    />

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-4 sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="نام یا شماره موبایل"
                icon="lucide.search"
                wire:model.live.debounce.300ms="search"
                clearable
            />
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>موبایل</th>
                        <th>سرورها</th>
                        <th>تاریخ عضویت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr wire:key="admin-user-{{ $user->id }}">
                            <td class="font-medium">{{ $user->name ?: 'بدون نام' }}</td>
                            <td class="font-mono text-xs" dir="ltr">{{ $user->phone }}</td>
                            <td>{{ number_format($user->servers_count) }}</td>
                            <td class="text-sm text-base-content/60">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-left">
                                <x-button
                                    label="جزئیات"
                                    icon="lucide.arrow-left"
                                    :link="route('admin.users.show', $user)"
                                    wire:navigate
                                    class="btn-ghost btn-sm"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-base-content/45">
                                کاربری پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="border-t border-base-300 p-4">
                {{ $users->links() }}
            </div>
        @endif
    </section>
</div>
