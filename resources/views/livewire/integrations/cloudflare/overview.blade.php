<div class="mx-auto w-full max-w-6xl space-y-6">
    @php
        $selectedZone = collect($zones)->firstWhere('id', $selectedZoneId);
    @endphp

    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-2xl">
            <a
                href="{{ route('panel.integrations.index') }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 text-xs font-medium text-base-content/45 transition hover:text-base-content/70"
            >
                <x-icon name="lucide.arrow-right" class="!size-3.5" />
                بازگشت به اتصال‌ها
            </a>

            <div class="mt-4 flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-icon name="lucide.cloud" class="!size-5 stroke-[1.8]" />
                </span>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight sm:text-[1.7rem]">Cloudflare</h1>

                        @if ($connected && ! $needsReconnect)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-success">
                                <span class="size-1.5 rounded-full bg-success"></span>
                                متصل
                            </span>
                        @endif

                        @if ($canManageDns)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-primary">
                                <x-icon name="lucide.shield-check" class="!size-3.5" />
                                DNS Management
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm leading-7 text-base-content/50">
                        حساب‌ها، دامنه‌ها و رکوردهای DNS را مشاهده و رکوردهای DNS را مستقیماً مدیریت کنید.
                    </p>
                </div>
            </div>
        </div>

        @if ($connected && ! $needsReconnect)
            <button
                type="button"
                wire:click="refreshData"
                wire:loading.attr="disabled"
                wire:target="refreshData"
                class="btn btn-ghost btn-sm rounded-xl"
            >
                <x-icon
                    name="lucide.refresh-cw"
                    class="!size-4"
                    wire:loading.class="animate-spin"
                    wire:target="refreshData"
                />
                همگام‌سازی مجدد
            </button>
        @endif
    </header>

    @if (! $connected)
        <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <x-icon name="lucide.link-2-off" class="!size-4.5" />
                </span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold">Cloudflare هنوز متصل نیست</h2>
                    <p class="mt-1 text-xs leading-6 text-base-content/50">
                        ابتدا اتصال OAuth را برقرار کنید تا Coreflare بتواند دامنه‌ها و DNS را مدیریت کند.
                    </p>

                    <a
                        href="{{ route('panel.integrations.cloudflare.redirect') }}"
                        class="btn btn-primary btn-sm mt-4 rounded-xl px-4"
                    >
                        اتصال Cloudflare
                    </a>
                </div>
            </div>
        </section>
    @elseif ($needsReconnect)
        <section class="rounded-3xl border border-warning/20 bg-warning/[0.05] p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <x-icon name="lucide.shield-alert" class="!size-4.5" />
                </span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold">دسترسی Cloudflare باید به‌روزرسانی شود</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-6 text-base-content/55">
                        اتصال فعلی مجوزهای خواندن لازم برای Account، Zone و DNS را ندارد. اتصال را دوباره برقرار کنید.
                    </p>

                    @if ($missingScopes !== [])
                        <div class="mt-3 flex flex-wrap gap-1.5" dir="ltr">
                            @foreach ($missingScopes as $scope)
                                <code class="rounded-lg bg-base-100 px-2 py-1 text-[11px] text-base-content/55">{{ $scope }}</code>
                            @endforeach
                        </div>
                    @endif

                    <a
                        href="{{ route('panel.integrations.cloudflare.redirect') }}"
                        class="btn btn-primary btn-sm mt-4 rounded-xl px-4"
                    >
                        به‌روزرسانی دسترسی
                    </a>
                </div>
            </div>
        </section>
    @else
        @if ($error)
            <div role="alert" class="flex items-start justify-between gap-3 rounded-2xl bg-error/[0.07] px-4 py-3 text-sm leading-7 text-error">
                <div class="flex items-start gap-2.5">
                    <x-icon name="lucide.triangle-alert" class="mt-1 !size-4.5 shrink-0" />
                    <span>{{ $error }}</span>
                </div>

                <button
                    type="button"
                    wire:click="refreshData"
                    class="btn btn-ghost btn-xs shrink-0 rounded-lg text-error"
                >
                    تلاش مجدد
                </button>
            </div>
        @endif

        @if ($dnsStatus)
            <div role="status" class="flex items-center gap-2.5 rounded-2xl bg-success/[0.07] px-4 py-3 text-sm text-success">
                <x-icon name="lucide.circle-check" class="!size-4.5 shrink-0" />
                <span>{{ $dnsStatus }}</span>
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">حساب‌ها</span>
                    <x-icon name="lucide.building-2" class="!size-4 text-base-content/30" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ count($accounts) }}</div>
            </div>

            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">دامنه‌ها</span>
                    <x-icon name="lucide.globe-2" class="!size-4 text-base-content/30" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ count($zones) }}</div>
            </div>

            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">DNS دامنه انتخاب‌شده</span>
                    <x-icon name="lucide.network" class="!size-4 text-base-content/30" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ count($dnsRecords) }}</div>
            </div>
        </section>

        <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold">حساب‌های Cloudflare</h2>
                    <p class="mt-1 text-xs leading-6 text-base-content/45">
                        حساب‌هایی که اتصال فعلی اجازه مشاهده آن‌ها را دارد.
                    </p>
                </div>

                @if ($lastSyncedAt)
                    <span class="inline-flex items-center gap-1.5 text-[11px] text-base-content/35" dir="ltr">
                        <x-icon name="lucide.clock-3" class="!size-3.5" />
                        {{ \Illuminate\Support\Carbon::parse($lastSyncedAt)->diffForHumans() }}
                    </span>
                @endif
            </div>

            @if ($accounts === [])
                <div class="mt-5 rounded-2xl bg-base-200/50 px-4 py-4 text-sm text-base-content/45">
                    حسابی از Cloudflare برگردانده نشد.
                </div>
            @else
                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($accounts as $account)
                        <div class="flex items-center gap-3 rounded-2xl border border-base-300/60 px-3.5 py-3" wire:key="cloudflare-account-{{ $account['id'] }}">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-base-200/70 text-base-content/45">
                                <x-icon name="lucide.building-2" class="!size-3.5" />
                            </span>
                            <span class="truncate text-sm font-medium">{{ $account['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="grid gap-5 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)]">
            <div class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
                <div>
                    <h2 class="text-sm font-semibold">دامنه‌ها</h2>
                    <p class="mt-1 text-xs leading-6 text-base-content/45">
                        یک دامنه را برای مشاهده و مدیریت رکوردهای DNS انتخاب کنید.
                    </p>
                </div>

                @if ($zones === [])
                    <div class="mt-5 rounded-2xl bg-base-200/50 px-4 py-4 text-sm text-base-content/45">
                        دامنه‌ای در این اتصال پیدا نشد.
                    </div>
                @else
                    <div class="mt-4 max-h-[42rem] space-y-2 overflow-y-auto pe-1">
                        @foreach ($zones as $zone)
                            @php
                                $isSelected = $selectedZoneId === $zone['id'];
                                $accountName = is_array($zone['account'] ?? null)
                                    ? ($zone['account']['name'] ?? null)
                                    : null;
                            @endphp

                            <button
                                type="button"
                                wire:click="selectZone('{{ $zone['id'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="selectZone"
                                class="w-full rounded-2xl border px-3.5 py-3 text-start transition {{ $isSelected ? 'border-primary/25 bg-primary/[0.05]' : 'border-base-300/60 hover:border-base-300 hover:bg-base-200/30' }}"
                                wire:key="cloudflare-zone-{{ $zone['id'] }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium" dir="ltr">{{ $zone['name'] }}</div>
                                        @if ($accountName)
                                            <div class="mt-1 truncate text-[11px] text-base-content/35">{{ $accountName }}</div>
                                        @endif
                                    </div>

                                    <span class="inline-flex shrink-0 items-center gap-1.5 text-[10px] font-medium {{ $zone['status'] === 'active' ? 'text-success' : 'text-warning' }}">
                                        <span class="size-1.5 rounded-full {{ $zone['status'] === 'active' ? 'bg-success' : 'bg-warning' }}"></span>
                                        {{ $zone['status'] === 'active' ? 'فعال' : $zone['status'] }}
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="min-w-0 space-y-4">
                <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold">رکوردهای DNS</h2>
                            @if (is_array($selectedZone))
                                <p class="mt-1 truncate text-xs text-base-content/45" dir="ltr">{{ $selectedZone['name'] }}</p>
                            @else
                                <p class="mt-1 text-xs text-base-content/45">ابتدا یک دامنه انتخاب کنید.</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <div
                                wire:loading.flex
                                wire:target="selectZone,refreshData,saveDnsRecord,deleteDnsRecord"
                                class="hidden items-center gap-1.5 text-[11px] text-base-content/40"
                            >
                                <span class="loading loading-spinner loading-xs"></span>
                                در حال انجام
                            </div>

                            @if (is_array($selectedZone) && $canManageDns)
                                <button
                                    type="button"
                                    wire:click="openCreateDnsRecord"
                                    class="btn btn-primary btn-sm rounded-xl px-3.5"
                                >
                                    <x-icon name="lucide.plus" class="!size-4" />
                                    افزودن رکورد
                                </button>
                            @endif
                        </div>
                    </div>

                    @if (is_array($selectedZone) && ! $canManageDns)
                        <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-warning/[0.06] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-2.5 text-xs leading-6 text-base-content/55">
                                <x-icon name="lucide.shield-alert" class="mt-1 !size-3.5 shrink-0 text-warning" />
                                <span>
                                    مشاهده DNS فعال است، اما برای ساخت، ویرایش و حذف رکوردها مجوز
                                    <code dir="ltr" class="rounded bg-base-100 px-1.5 py-0.5">dns.write</code>
                                    لازم است.
                                </span>
                            </div>

                            <a
                                href="{{ route('panel.integrations.cloudflare.redirect') }}"
                                class="btn btn-outline btn-sm shrink-0 rounded-xl"
                            >
                                به‌روزرسانی دسترسی
                            </a>
                        </div>
                    @endif

                    @if (! is_array($selectedZone))
                        <div class="mt-5 rounded-2xl bg-base-200/50 px-4 py-8 text-center text-sm text-base-content/45">
                            دامنه‌ای برای نمایش DNS انتخاب نشده است.
                        </div>
                    @elseif ($dnsRecords === [])
                        <div class="mt-5 rounded-2xl bg-base-200/50 px-4 py-8 text-center text-sm text-base-content/45">
                            رکورد DNS قابل نمایشی پیدا نشد.
                            @if ($canManageDns)
                                <div class="mt-3">
                                    <button
                                        type="button"
                                        wire:click="openCreateDnsRecord"
                                        class="btn btn-ghost btn-sm rounded-xl"
                                    >
                                        اولین رکورد را اضافه کنید
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-4 overflow-hidden rounded-2xl border border-base-300/60">
                            <div class="hidden grid-cols-[4.5rem_minmax(9rem,1fr)_minmax(11rem,1.25fr)_5rem_6.5rem] gap-3 border-b border-base-300/60 bg-base-200/35 px-3.5 py-2.5 text-[10px] font-medium text-base-content/40 md:grid">
                                <span>نوع</span>
                                <span>نام</span>
                                <span>مقدار</span>
                                <span>Proxy</span>
                                <span>عملیات</span>
                            </div>

                            <div class="divide-y divide-base-300/50">
                                @foreach ($dnsRecords as $record)
                                    @php
                                        $manageableType = in_array($record['type'], $manageableDnsTypes, true);
                                    @endphp

                                    <div class="grid gap-2 px-3.5 py-3 md:grid-cols-[4.5rem_minmax(9rem,1fr)_minmax(11rem,1.25fr)_5rem_6.5rem] md:items-start md:gap-3" wire:key="cloudflare-dns-{{ $record['id'] }}">
                                        <div>
                                            <span class="inline-flex min-w-10 justify-center rounded-lg bg-base-200 px-2 py-1 font-mono text-[10px] font-semibold text-base-content/65">
                                                {{ $record['type'] }}
                                            </span>
                                        </div>

                                        <div class="min-w-0 break-all font-mono text-xs leading-6 text-base-content/65" dir="ltr">
                                            {{ $record['name'] }}
                                        </div>

                                        <div class="min-w-0 break-all font-mono text-xs leading-6 text-base-content/55" dir="ltr">
                                            {{ $record['content'] }}
                                            @if ($record['priority'] !== null)
                                                <span class="ms-1 text-[10px] text-base-content/30">priority {{ $record['priority'] }}</span>
                                            @endif
                                        </div>

                                        <div class="text-[11px]">
                                            @if ($record['proxied'] === true)
                                                <span class="inline-flex items-center gap-1.5 text-warning">
                                                    <span class="size-1.5 rounded-full bg-warning"></span>
                                                    روشن
                                                </span>
                                            @elseif ($record['proxied'] === false && ($record['proxiable'] ?? false))
                                                <span class="inline-flex items-center gap-1.5 text-base-content/40">
                                                    <span class="size-1.5 rounded-full bg-base-content/25"></span>
                                                    خاموش
                                                </span>
                                            @else
                                                <span class="text-base-content/30">—</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-1">
                                            @if ($canManageDns && $manageableType)
                                                <button
                                                    type="button"
                                                    wire:click="editDnsRecord('{{ $record['id'] }}')"
                                                    class="btn btn-ghost btn-xs rounded-lg"
                                                    title="ویرایش"
                                                >
                                                    <x-icon name="lucide.pencil" class="!size-3.5" />
                                                </button>
                                            @endif

                                            @if ($canManageDns)
                                                <button
                                                    type="button"
                                                    wire:click="deleteDnsRecord('{{ $record['id'] }}')"
                                                    wire:confirm="این رکورد DNS برای همیشه از Cloudflare حذف شود؟"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteDnsRecord"
                                                    class="btn btn-ghost btn-xs rounded-lg text-error"
                                                    title="حذف"
                                                >
                                                    <x-icon name="lucide.trash-2" class="!size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                @if ($dnsFormOpen && is_array($selectedZone) && $canManageDns)
                    <section class="rounded-3xl border border-primary/15 bg-base-100 p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold">
                                    {{ $editingDnsRecordId ? 'ویرایش رکورد DNS' : 'رکورد DNS جدید' }}
                                </h3>
                                <p class="mt-1 text-xs leading-6 text-base-content/45">
                                    نام کوتاه مثل <code dir="ltr">www</code> به‌صورت خودکار به دامنه انتخاب‌شده متصل می‌شود؛ برای ریشه از <code dir="ltr">@</code> استفاده کنید.
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="cancelDnsRecordForm"
                                class="btn btn-ghost btn-xs rounded-lg"
                            >
                                <x-icon name="lucide.x" class="!size-4" />
                            </button>
                        </div>

                        <form wire:submit="saveDnsRecord" class="mt-5 space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="form-control gap-1.5">
                                    <span class="text-xs font-medium text-base-content/60">نوع رکورد</span>
                                    <select wire:model.live="dnsType" class="select select-bordered select-sm w-full rounded-xl">
                                        @foreach ($manageableDnsTypes as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    @error('dnsType')
                                        <span class="text-[11px] text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control gap-1.5">
                                    <span class="text-xs font-medium text-base-content/60">نام</span>
                                    <input
                                        type="text"
                                        wire:model="dnsName"
                                        class="input input-bordered input-sm w-full rounded-xl font-mono"
                                        dir="ltr"
                                        placeholder="@ یا www"
                                    />
                                    @error('dnsName')
                                        <span class="text-[11px] text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>

                            <label class="form-control gap-1.5">
                                <span class="text-xs font-medium text-base-content/60">مقدار</span>
                                <input
                                    type="text"
                                    wire:model="dnsContent"
                                    class="input input-bordered input-sm w-full rounded-xl font-mono"
                                    dir="ltr"
                                    placeholder="{{ $dnsType === 'A' ? '203.0.113.10' : ($dnsType === 'CNAME' ? 'target.example.com' : 'مقدار رکورد') }}"
                                />
                                @error('dnsContent')
                                    <span class="text-[11px] text-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="form-control gap-1.5">
                                    <span class="text-xs font-medium text-base-content/60">TTL</span>
                                    <select wire:model="dnsTtl" class="select select-bordered select-sm w-full rounded-xl">
                                        <option value="1">Auto</option>
                                        <option value="60">1 دقیقه</option>
                                        <option value="300">5 دقیقه</option>
                                        <option value="900">15 دقیقه</option>
                                        <option value="3600">1 ساعت</option>
                                        <option value="14400">4 ساعت</option>
                                        <option value="86400">1 روز</option>
                                    </select>
                                    @error('dnsTtl')
                                        <span class="text-[11px] text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                @if ($dnsType === 'MX')
                                    <label class="form-control gap-1.5">
                                        <span class="text-xs font-medium text-base-content/60">Priority</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="65535"
                                            wire:model="dnsPriority"
                                            class="input input-bordered input-sm w-full rounded-xl"
                                            dir="ltr"
                                            placeholder="10"
                                        />
                                        @error('dnsPriority')
                                            <span class="text-[11px] text-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                @endif

                                @if (in_array($dnsType, ['A', 'AAAA', 'CNAME'], true))
                                    <label class="flex items-center justify-between gap-3 rounded-xl border border-base-300/70 px-3 py-2.5">
                                        <span>
                                            <span class="block text-xs font-medium text-base-content/60">Cloudflare Proxy</span>
                                            <span class="mt-0.5 block text-[10px] text-base-content/35">ابر نارنجی</span>
                                        </span>
                                        <input type="checkbox" wire:model="dnsProxied" class="toggle toggle-sm toggle-warning" />
                                    </label>
                                @endif
                            </div>

                            <label class="form-control gap-1.5">
                                <span class="text-xs font-medium text-base-content/60">توضیح <span class="font-normal text-base-content/35">(اختیاری)</span></span>
                                <input
                                    type="text"
                                    wire:model="dnsComment"
                                    class="input input-bordered input-sm w-full rounded-xl"
                                    placeholder="مثلاً رکورد سرویس n8n"
                                />
                                @error('dnsComment')
                                    <span class="text-[11px] text-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                <button
                                    type="button"
                                    wire:click="cancelDnsRecordForm"
                                    class="btn btn-ghost btn-sm rounded-xl"
                                >
                                    انصراف
                                </button>

                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="saveDnsRecord"
                                    class="btn btn-primary btn-sm rounded-xl px-4"
                                >
                                    <span wire:loading.remove wire:target="saveDnsRecord">
                                        {{ $editingDnsRecordId ? 'ذخیره تغییرات' : 'ساخت رکورد' }}
                                    </span>
                                    <span wire:loading.flex wire:target="saveDnsRecord" class="hidden items-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        در حال ذخیره
                                    </span>
                                </button>
                            </div>
                        </form>
                    </section>
                @endif
            </div>
        </section>

        <div class="flex items-start gap-2.5 rounded-2xl bg-base-200/40 px-4 py-3 text-xs leading-6 text-base-content/45">
            <x-icon name="lucide.shield-check" class="mt-1 !size-3.5 shrink-0" />
            <span>
                خواندن اطلاعات با مجوزهای Read انجام می‌شود و عملیات ساخت، ویرایش و حذف رکورد DNS فقط با مجوز مستقل
                <code dir="ltr">dns.write</code>
                روی Zone انتخاب‌شده اجرا می‌شود. در این فاز ایجاد و ویرایش مستقیم رکوردهای A، AAAA، CNAME، TXT و MX پشتیبانی می‌شود.
            </span>
        </div>
    @endif
</div>
