<div class="mx-auto w-full max-w-6xl space-y-6">
    @php
        $selectedZone = collect($zones)->firstWhere('id', $selectedZoneId);
        $activeZones = collect($zones)->where('status', 'active')->count();
        $pendingZones = collect($zones)->where('status', 'pending')->count();
        $selectedStatus = is_array($selectedZone)
            ? ($selectedZone['status'] ?? 'unknown')
            : null;
        $selectedNameServers = is_array($selectedZone)
            && is_array($selectedZone['name_servers'] ?? null)
                ? $selectedZone['name_servers']
                : [];
    @endphp

    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-2xl">
            <a
                href="{{ route('panel.integrations.cloudflare.overview') }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 text-xs font-medium text-base-content/45 transition hover:text-base-content/70"
            >
                <x-icon name="lucide.arrow-right" class="!size-3.5" />
                بازگشت به Cloudflare
            </a>

            <div class="mt-4 flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-icon name="lucide.globe-2" class="!size-5 stroke-[1.8]" />
                </span>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight sm:text-[1.7rem]">دامنه‌های Cloudflare</h1>

                        @if ($connected && ! $needsReconnect)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-success">
                                <span class="size-1.5 rounded-full bg-success"></span>
                                متصل
                            </span>
                        @endif

                        @if ($canManageZones)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-primary">
                                <x-icon name="lucide.shield-check" class="!size-3.5" />
                                Zone Management
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm leading-7 text-base-content/50">
                        دامنه را به Cloudflare اضافه کنید، Nameserverها را دریافت کنید و وضعیت فعال‌سازی را از Coreflare پیگیری کنید.
                    </p>
                </div>
            </div>
        </div>

        @if ($connected && ! $needsReconnect)
            <div class="flex flex-wrap items-center gap-2">
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
                    بررسی مجدد
                </button>

                @if ($canManageZones)
                    <button
                        type="button"
                        wire:click="openCreateZone"
                        class="btn btn-primary btn-sm rounded-xl px-4"
                    >
                        <x-icon name="lucide.plus" class="!size-4" />
                        افزودن دامنه
                    </button>
                @endif
            </div>
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
                        ابتدا اتصال OAuth را برقرار کنید تا مدیریت دامنه‌ها فعال شود.
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
                        اتصال فعلی مجوزهای خواندن موردنیاز برای Account، Zone و DNS را ندارد.
                    </p>

                    @if ($missingReadScopes !== [])
                        <div class="mt-3 flex flex-wrap gap-1.5" dir="ltr">
                            @foreach ($missingReadScopes as $scope)
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
        @if (! $canManageZones)
            <section class="rounded-3xl border border-warning/20 bg-warning/[0.05] p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3.5">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                            <x-icon name="lucide.shield-plus" class="!size-4.5" />
                        </span>

                        <div>
                            <h2 class="text-sm font-semibold">Zone Write هنوز فعال نیست</h2>
                            <p class="mt-1 text-xs leading-6 text-base-content/55">
                                مشاهده دامنه‌ها فعال است، اما برای افزودن و حذف Zone مجوز
                                <code dir="ltr" class="rounded bg-base-100 px-1.5 py-0.5">zone.write</code>
                                لازم است.
                            </p>

                            @if ($missingZoneManagementScopes !== [])
                                <div class="mt-2 flex flex-wrap gap-1.5" dir="ltr">
                                    @foreach ($missingZoneManagementScopes as $scope)
                                        <code class="rounded-lg bg-base-100 px-2 py-1 text-[11px] text-base-content/50">{{ $scope }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <a
                        href="{{ route('panel.integrations.cloudflare.redirect') }}"
                        class="btn btn-primary btn-sm shrink-0 rounded-xl px-4"
                    >
                        فعال‌سازی Zone Write
                    </a>
                </div>
            </section>
        @endif

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

        @if ($status)
            <div role="status" class="flex items-start gap-2.5 rounded-2xl bg-success/[0.07] px-4 py-3 text-sm leading-7 text-success">
                <x-icon name="lucide.circle-check" class="mt-1 !size-4.5 shrink-0" />
                <span>{{ $status }}</span>
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">کل دامنه‌ها</span>
                    <x-icon name="lucide.globe-2" class="!size-4 text-base-content/30" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ count($zones) }}</div>
            </div>

            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">فعال</span>
                    <x-icon name="lucide.circle-check" class="!size-4 text-success/60" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $activeZones }}</div>
            </div>

            <div class="rounded-2xl border border-base-300/70 bg-base-100 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-base-content/45">در انتظار</span>
                    <x-icon name="lucide.clock-3" class="!size-4 text-warning/70" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $pendingZones }}</div>
            </div>
        </section>

        @if ($zoneFormOpen)
            <section class="rounded-3xl border border-primary/15 bg-primary/[0.025] p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold">افزودن دامنه به Cloudflare</h2>
                        <p class="mt-1 text-xs leading-6 text-base-content/45">
                            Root domain را وارد کنید. Cloudflare پس از ساخت Zone دو Nameserver اختصاص می‌دهد.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cancelCreateZone"
                        class="btn btn-ghost btn-xs rounded-lg"
                    >
                        <x-icon name="lucide.x" class="!size-4" />
                    </button>
                </div>

                <form wire:submit="createZone" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)_auto] lg:items-end">
                    <label class="form-control w-full">
                        <span class="mb-1.5 text-xs font-medium text-base-content/60">حساب Cloudflare</span>
                        <select wire:model="zoneAccountId" class="select select-bordered w-full rounded-xl bg-base-100">
                            @foreach ($accounts as $account)
                                <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                            @endforeach
                        </select>
                        @error('zoneAccountId')
                            <span class="mt-1.5 text-xs text-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="form-control w-full">
                        <span class="mb-1.5 text-xs font-medium text-base-content/60">دامنه</span>
                        <input
                            type="text"
                            wire:model="zoneDomain"
                            dir="ltr"
                            autocomplete="off"
                            placeholder="example.com"
                            class="input input-bordered w-full rounded-xl bg-base-100"
                        />
                        @error('zoneDomain')
                            <span class="mt-1.5 text-xs text-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="createZone"
                        class="btn btn-primary rounded-xl px-5"
                    >
                        <span wire:loading.remove wire:target="createZone">ساخت Zone</span>
                        <span wire:loading.inline-flex wire:target="createZone" class="hidden items-center gap-2">
                            <span class="loading loading-spinner loading-xs"></span>
                            در حال ساخت
                        </span>
                    </button>
                </form>
            </section>
        @endif

        <section class="grid gap-5 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)]">
            <div class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">دامنه‌ها</h2>
                        <p class="mt-1 text-xs leading-6 text-base-content/45">
                            وضعیت Zone مستقیماً از Cloudflare دریافت می‌شود.
                        </p>
                    </div>

                    @if ($lastSyncedAt)
                        <span class="text-[10px] text-base-content/35" dir="ltr">
                            {{ \Illuminate\Support\Carbon::parse($lastSyncedAt)->diffForHumans() }}
                        </span>
                    @endif
                </div>

                @if ($zones === [])
                    <div class="mt-5 rounded-2xl bg-base-200/50 px-4 py-5 text-sm leading-7 text-base-content/45">
                        هنوز دامنه‌ای در این اتصال وجود ندارد.
                        @if ($canManageZones)
                            <button type="button" wire:click="openCreateZone" class="ms-1 font-medium text-primary hover:underline">
                                اولین دامنه را اضافه کنید.
                            </button>
                        @endif
                    </div>
                @else
                    <div class="mt-4 max-h-[42rem] space-y-2 overflow-y-auto pe-1">
                        @foreach ($zones as $zone)
                            @php
                                $isSelected = $selectedZoneId === $zone['id'];
                                $zoneStatus = $zone['status'] ?? 'unknown';
                                $statusClass = $zoneStatus === 'active'
                                    ? 'text-success'
                                    : ($zoneStatus === 'pending' || $zoneStatus === 'initializing'
                                        ? 'text-warning'
                                        : 'text-base-content/45');
                                $dotClass = $zoneStatus === 'active'
                                    ? 'bg-success'
                                    : ($zoneStatus === 'pending' || $zoneStatus === 'initializing'
                                        ? 'bg-warning'
                                        : 'bg-base-content/30');
                            @endphp

                            <button
                                type="button"
                                wire:click="selectZone('{{ $zone['id'] }}')"
                                class="w-full rounded-2xl border px-3.5 py-3 text-start transition {{ $isSelected ? 'border-primary/25 bg-primary/[0.05]' : 'border-base-300/60 hover:border-base-300 hover:bg-base-200/30' }}"
                                wire:key="cloudflare-zone-onboarding-{{ $zone['id'] }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium" dir="ltr">{{ $zone['name'] }}</div>
                                        @if (is_array($zone['account'] ?? null) && filled($zone['account']['name'] ?? null))
                                            <div class="mt-1 truncate text-[11px] text-base-content/35">{{ $zone['account']['name'] }}</div>
                                        @endif
                                    </div>

                                    <span class="inline-flex shrink-0 items-center gap-1.5 text-[10px] font-medium {{ $statusClass }}">
                                        <span class="size-1.5 rounded-full {{ $dotClass }}"></span>
                                        {{ match ($zoneStatus) {
                                            'active' => 'فعال',
                                            'pending' => 'در انتظار',
                                            'initializing' => 'در حال آماده‌سازی',
                                            'moved' => 'منتقل‌شده',
                                            default => 'نامشخص',
                                        } }}
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="min-w-0">
                <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
                    @if (! is_array($selectedZone))
                        <div class="flex min-h-52 flex-col items-center justify-center text-center">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-base-200/70 text-base-content/35">
                                <x-icon name="lucide.globe-2" class="!size-5" />
                            </span>
                            <h2 class="mt-3 text-sm font-semibold">دامنه‌ای انتخاب نشده است</h2>
                            <p class="mt-1 text-xs leading-6 text-base-content/45">برای مشاهده وضعیت و Nameserverها یک دامنه را انتخاب کنید.</p>
                        </div>
                    @else
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold" dir="ltr">{{ $selectedZone['name'] }}</h2>

                                    <span class="rounded-lg px-2 py-1 text-[10px] font-medium {{ $selectedStatus === 'active' ? 'bg-success/10 text-success' : ($selectedStatus === 'pending' || $selectedStatus === 'initializing' ? 'bg-warning/10 text-warning' : 'bg-base-200 text-base-content/45') }}">
                                        {{ match ($selectedStatus) {
                                            'active' => 'فعال',
                                            'pending' => 'در انتظار فعال‌سازی',
                                            'initializing' => 'در حال آماده‌سازی',
                                            'moved' => 'منتقل‌شده',
                                            default => 'وضعیت نامشخص',
                                        } }}
                                    </span>
                                </div>

                                @if (is_array($selectedZone['account'] ?? null))
                                    <p class="mt-1 text-xs text-base-content/40">{{ $selectedZone['account']['name'] ?? '' }}</p>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="refreshData"
                                wire:loading.attr="disabled"
                                wire:target="refreshData"
                                class="btn btn-ghost btn-sm shrink-0 rounded-xl"
                            >
                                <x-icon name="lucide.refresh-cw" class="!size-4" />
                                بررسی وضعیت
                            </button>
                        </div>

                        @if ($selectedStatus === 'active')
                            <div class="mt-5 rounded-2xl bg-success/[0.06] p-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                                        <x-icon name="lucide.circle-check" class="!size-4" />
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-success">دامنه فعال است</h3>
                                        <p class="mt-1 text-xs leading-6 text-base-content/50">
                                            Cloudflare delegation را تأیید کرده و Zone آماده مدیریت DNS و انتشار سرویس است.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedStatus === 'pending' || $selectedStatus === 'initializing')
                            <div class="mt-5 rounded-2xl bg-warning/[0.06] p-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                                        <x-icon name="lucide.clock-3" class="!size-4" />
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold">در انتظار تغییر Nameserver</h3>
                                        <p class="mt-1 text-xs leading-6 text-base-content/50">
                                            Nameserverهای زیر را در پنل ثبت‌کننده دامنه جایگزین کنید. فعال‌شدن Zone ممکن است کمی زمان ببرد.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-xs font-semibold text-base-content/65">Nameserverهای Cloudflare</h3>
                                <span class="text-[10px] text-base-content/35">Delegation</span>
                            </div>

                            @if ($selectedNameServers === [])
                                <div class="mt-2 rounded-2xl bg-base-200/45 px-4 py-3 text-xs text-base-content/45">
                                    Cloudflare هنوز Nameserverی برای این Zone برنگردانده است. وضعیت را دوباره بررسی کنید.
                                </div>
                            @else
                                <div class="mt-2 space-y-2" dir="ltr">
                                    @foreach ($selectedNameServers as $nameServer)
                                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-base-300/60 px-3.5 py-3">
                                            <code class="truncate text-xs text-base-content/70">{{ $nameServer }}</code>
                                            <x-icon name="lucide.server" class="!size-3.5 shrink-0 text-base-content/30" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-base-200/35 px-4 py-3">
                                <div class="text-[10px] text-base-content/40">Zone ID</div>
                                <code class="mt-1 block truncate text-[11px] text-base-content/60" dir="ltr">{{ $selectedZone['id'] }}</code>
                            </div>

                            <div class="rounded-2xl bg-base-200/35 px-4 py-3">
                                <div class="text-[10px] text-base-content/40">نوع Zone</div>
                                <div class="mt-1 text-xs font-medium" dir="ltr">{{ $selectedZone['type'] ?? 'unknown' }}</div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 border-t border-base-300/60 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($selectedStatus === 'active')
                                    <a
                                        href="{{ route('panel.integrations.cloudflare.overview') }}"
                                        wire:navigate
                                        class="btn btn-primary btn-sm rounded-xl px-4"
                                    >
                                        <x-icon name="lucide.network" class="!size-4" />
                                        مدیریت DNS
                                    </a>
                                @endif

                                <button
                                    type="button"
                                    wire:click="refreshData"
                                    wire:loading.attr="disabled"
                                    wire:target="refreshData"
                                    class="btn btn-outline btn-sm rounded-xl"
                                >
                                    بررسی مجدد
                                </button>
                            </div>

                            @if ($canManageZones)
                                @if ($pendingDeleteZoneId === $selectedZone['id'])
                                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-error/[0.06] p-2">
                                        <span class="px-1 text-xs text-error">حذف Zone تأیید شود؟</span>
                                        <button
                                            type="button"
                                            wire:click="deleteZone"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteZone"
                                            class="btn btn-error btn-xs rounded-lg"
                                        >
                                            حذف نهایی
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="cancelDeleteZone"
                                            class="btn btn-ghost btn-xs rounded-lg"
                                        >
                                            انصراف
                                        </button>
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        wire:click="confirmDeleteZone('{{ $selectedZone['id'] }}')"
                                        class="btn btn-ghost btn-sm rounded-xl text-error"
                                    >
                                        <x-icon name="lucide.trash-2" class="!size-4" />
                                        حذف از Cloudflare
                                    </button>
                                @endif
                            @endif
                        </div>
                    @endif
                </section>
            </div>
        </section>
    @endif
</div>
