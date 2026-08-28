@props(['server'])

<div class="space-y-4">
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

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex items-center gap-2.5 border-b border-base-300 px-5 py-4">
            <span class="flex size-9 items-center justify-center rounded-xl bg-warning/10 text-warning">
                <x-icon name="lucide.refresh-cw" class="!size-4.5 stroke-[1.7]" />
            </span>
            <div>
                <h2 class="text-sm font-semibold">اصلاح IP سرور</h2>
                <p class="mt-0.5 text-xs text-base-content/45">همگام‌سازی آدرس اتصال Coreflare با IP جدید اعلام‌شده توسط ارائه‌دهنده</p>
            </div>
        </div>

        <div class="grid gap-4 p-4 lg:grid-cols-[minmax(220px,0.65fr)_minmax(0,1fr)_auto] lg:items-end sm:p-5">
            <label class="form-control w-full">
                <span class="mb-2 text-xs font-medium text-base-content/60">IP جدید</span>
                <input
                    type="text"
                    wire:model.blur="newHost"
                    class="input input-bordered w-full rounded-xl font-mono"
                    dir="ltr"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="203.0.113.10"
                />
                @error('newHost')
                    <span class="mt-1.5 text-xs text-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="form-control w-full">
                <span class="mb-2 text-xs font-medium text-base-content/60">دلیل تغییر</span>
                <input
                    type="text"
                    wire:model.blur="hostUpdateReason"
                    class="input input-bordered w-full rounded-xl"
                    maxlength="500"
                    autocomplete="off"
                    placeholder="مثلاً: IP قبلی مسدود بود و Provider آدرس جدید اعلام کرد"
                />
                @error('hostUpdateReason')
                    <span class="mt-1.5 text-xs text-error">{{ $message }}</span>
                @enderror
            </label>

            <button
                type="button"
                wire:click="updateServerConnectionHost"
                wire:loading.attr="disabled"
                wire:target="updateServerConnectionHost"
                class="btn btn-warning min-w-36"
            >
                <span wire:loading.remove wire:target="updateServerConnectionHost">ذخیره IP جدید</span>
                <span wire:loading wire:target="updateServerConnectionHost" class="loading loading-spinner loading-xs"></span>
            </button>
        </div>

        <div class="border-t border-base-300 px-4 py-3 sm:px-5">
            <p class="text-xs leading-6 text-base-content/50">
                این عملیات فقط آدرس ذخیره‌شده در Coreflare را تغییر می‌دهد و هیچ درخواست تغییر IP به Cloud Provider ارسال نمی‌کند. تغییر موفق در Audit ثبت می‌شود.
            </p>
        </div>

        <div class="border-t border-base-300">
            <div class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                <div>
                    <h3 class="text-sm font-semibold">سوابق تغییر IP</h3>
                    <p class="mt-0.5 text-xs text-base-content/45">۱۰ تغییر اخیر ثبت‌شده برای این سرور</p>
                </div>
                <x-icon name="lucide.history" class="!size-4 text-base-content/35" />
            </div>

            @if ($server->connectionHostUpdateLogs->isEmpty())
                <div class="border-t border-base-300 px-5 py-6 text-center text-sm text-base-content/45">
                    هنوز تغییری برای IP این سرور ثبت نشده است.
                </div>
            @else
                <div class="overflow-x-auto border-t border-base-300">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>تغییر IP</th>
                            <th>مدیر</th>
                            <th>دلیل</th>
                            <th>زمان</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($server->connectionHostUpdateLogs as $changeLog)
                            @php
                                $matches = [];
                                $parsed = preg_match(
                                    '/^IP:\s*(?<old>[0-9.]+)\s*→\s*(?<new>[0-9.]+)\s*\|\s*(?<reason>.*)$/u',
                                    $changeLog->reason,
                                    $matches,
                                ) === 1;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap">
                                    @if ($parsed)
                                        <div class="flex items-center gap-2 font-mono text-xs" dir="ltr">
                                            <span>{{ $matches['old'] }}</span>
                                            <x-icon name="lucide.arrow-right" class="!size-3.5 text-base-content/35" />
                                            <span class="font-semibold text-warning">{{ $matches['new'] }}</span>
                                        </div>
                                    @else
                                        <span class="text-sm text-base-content/50">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm">
                                    {{ $changeLog->adminUser?->name ?: $changeLog->adminUser?->phone ?: '—' }}
                                </td>
                                <td class="max-w-md text-sm text-base-content/65">
                                    {{ $parsed ? $matches['reason'] : $changeLog->reason }}
                                </td>
                                <td class="whitespace-nowrap text-xs text-base-content/50">
                                    <x-persian-date :date="$changeLog->created_at" />
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>
