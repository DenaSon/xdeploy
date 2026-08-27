<div class="space-y-5">
    <x-admin.page-header
        title="بررسی Volumeها"
        description="گزارش Volumeهای ArvanCloud، ارتباط آن‌ها با سرورهای Coreflare و حذف دستی موارد امن."
        icon="lucide.hard-drive"
    >
        <x-slot:actions>
            <button
                type="button"
                class="btn btn-primary btn-sm"
                wire:click="refreshAudit"
                wire:loading.attr="disabled"
                wire:target="refreshAudit"
            >
                <x-icon name="lucide.refresh-cw" class="!size-4" />
                بازبینی مجدد
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($message !== null)
        <div class="flex items-start gap-2 rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="mt-0.5 !size-4 shrink-0" />
            <span>{{ $message }}</span>
        </div>
    @endif

    @if($error !== null)
        <div class="flex items-start gap-2 rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            <x-icon name="lucide.circle-alert" class="mt-0.5 !size-4 shrink-0" />
            <span>{{ $error }}</span>
        </div>
    @endif

    @php
        $allItems = collect($items);
        $linkedCount = $allItems->where('audit_status', 'linked')->count();
        $detachedCount = $allItems->where('audit_status', 'detached')->count();
        $orphanCount = $allItems->where('audit_status', 'orphan')->count();
        $ambiguousCount = $allItems->where('audit_status', 'ambiguous')->count();
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">متصل و شناخته‌شده</div>
            <div class="mt-2 text-2xl font-semibold">{{ $linkedCount }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs text-base-content/50">Detached با سابقه</div>
            <div class="mt-2 text-2xl font-semibold">{{ $detachedCount }}</div>
        </div>
        <div class="rounded-2xl border border-warning/25 bg-warning/5 p-4">
            <div class="text-xs text-warning">Orphan</div>
            <div class="mt-2 text-2xl font-semibold text-warning">{{ $orphanCount }}</div>
        </div>
        <div class="rounded-2xl border border-error/20 bg-error/5 p-4">
            <div class="text-xs text-error">نیازمند بررسی</div>
            <div class="mt-2 text-2xl font-semibold text-error">{{ $ambiguousCount }}</div>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-3 border-b border-base-300 p-4 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="Volume ID، نام یا Server ID"
                icon="lucide.search"
                wire:model.live.debounce.250ms="search"
                clearable
            />

            <label class="form-control">
                <span class="label-text mb-2 text-xs">وضعیت Audit</span>
                <select class="select select-bordered w-full" wire:model.live="filter">
                    <option value="all">همه Volumeها</option>
                    <option value="linked">Linked</option>
                    <option value="detached">Detached</option>
                    <option value="orphan">Orphan</option>
                    <option value="ambiguous">Ambiguous</option>
                </select>
            </label>

            <div class="pb-2 text-xs text-base-content/45 sm:text-left">
                آخرین بررسی: {{ $lastCheckedAt ?? '—' }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Volume</th>
                        <th>Audit</th>
                        <th>سرور مرتبط</th>
                        <th>Provider Server</th>
                        <th>Region</th>
                        <th>وضعیت Provider</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visibleItems as $item)
                        @php
                            $auditMeta = match($item['audit_status']) {
                                'linked' => ['label' => 'Linked', 'class' => 'badge-success'],
                                'detached' => ['label' => 'Detached', 'class' => 'badge-warning'],
                                'orphan' => ['label' => 'Orphan', 'class' => 'badge-error'],
                                default => ['label' => 'Ambiguous', 'class' => 'badge-ghost'],
                            };

                            $providerServerId = $item['attachment_server_id']
                                ?? $item['coreflare_provider_server_id']
                                ?? null;
                        @endphp

                        <tr wire:key="admin-volume-audit-{{ $item['region_id'] }}-{{ $item['volume_id'] }}">
                            <td>
                                <div class="font-medium">{{ $item['volume_name'] ?: 'بدون نام' }}</div>
                                <div class="mt-1 font-mono text-xs text-base-content/45" dir="ltr">{{ $item['volume_id'] }}</div>
                            </td>
                            <td>
                                <span class="badge badge-sm {{ $auditMeta['class'] }}">{{ $auditMeta['label'] }}</span>
                            </td>
                            <td>
                                @if($item['coreflare_server_id'] !== null)
                                    <div class="flex flex-col gap-1">
                                        @if(! $item['coreflare_server_deleted'])
                                            <a
                                                class="link link-hover font-medium"
                                                href="{{ route('admin.servers.show', ['adminServer' => $item['coreflare_server_id']]) }}"
                                                wire:navigate
                                            >
                                                {{ $item['coreflare_server_name'] ?: 'Server' }} #{{ $item['coreflare_server_id'] }}
                                            </a>
                                        @else
                                            <span class="font-medium">{{ $item['coreflare_server_name'] ?: 'Server' }} #{{ $item['coreflare_server_id'] }}</span>
                                        @endif

                                        <div class="flex flex-wrap gap-1">
                                            @if($item['coreflare_server_deleted'] || $item['coreflare_server_terminated'])
                                                <span class="badge badge-ghost badge-xs">حذف‌شده</span>
                                            @elseif($item['coreflare_server_status'] === 'active')
                                                <span class="badge badge-success badge-xs">فعال</span>
                                            @elseif($item['coreflare_server_status'] === 'inactive')
                                                <span class="badge badge-ghost badge-xs">غیرفعال</span>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($item['attachment_server_id'] !== null)
                                    <span class="text-xs text-warning">در Coreflare پیدا نشد</span>
                                @else
                                    <span class="text-base-content/35">—</span>
                                @endif
                            </td>
                            <td>
                                @if($providerServerId !== null)
                                    <div class="font-mono text-xs" dir="ltr">{{ $providerServerId }}</div>
                                    @if($item['attachment_server_name'] !== null)
                                        <div class="mt-1 text-xs text-base-content/45">{{ $item['attachment_server_name'] }}</div>
                                    @endif
                                @else
                                    <span class="text-base-content/35">—</span>
                                @endif
                            </td>
                            <td><span class="font-mono text-xs" dir="ltr">{{ $item['region_id'] }}</span></td>
                            <td><span class="badge badge-ghost badge-sm">{{ $item['volume_status'] }}</span></td>
                            <td class="text-left">
                                @if($item['can_delete'])
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm text-error"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteVolume"
                                        x-on:click="if (confirm('این Volume مستقیماً از ArvanCloud حذف می‌شود و قابل بازیابی نیست. ادامه می‌دهید؟')) $wire.deleteVolume(@js($item['region_id']), @js($item['volume_id']))"
                                    >
                                        <x-icon name="lucide.trash-2" class="!size-4" />
                                        حذف
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-base-content/35">
                                        <x-icon name="lucide.shield" class="!size-3.5" />
                                        محافظت‌شده
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-sm text-base-content/45">
                                Volumeای برای نمایش پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3 text-xs leading-6 text-base-content/55">
        حذف خودکار وجود ندارد. Volumeهای متصل یا Ambiguous قابل حذف نیستند. برای Volumeهای Detached و Orphan، حذف فقط با تأیید دستی ادمین انجام می‌شود.
    </div>
</div>
