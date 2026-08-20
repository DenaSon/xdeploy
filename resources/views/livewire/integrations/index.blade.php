<div class="mx-auto w-full max-w-5xl space-y-6">
    <header class="max-w-2xl">
        <div class="flex items-center gap-2 text-sm font-medium text-primary">
            <span class="flex size-8 items-center justify-center rounded-xl bg-primary/10">
                <x-icon name="lucide.link-2" class="!size-4 stroke-[1.8]" />
            </span>
            یکپارچه‌سازی‌ها
        </div>

        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
            سرویس‌های موردنیاز را به Coreflare متصل کنید
        </h1>

        <p class="mt-2 text-sm leading-7 text-base-content/50">
            سرویس‌های خارجی را یک‌بار متصل کنید و مدیریت قابلیت‌های آن‌ها را از فضای اختصاصی همان سرویس ادامه دهید.
        </p>
    </header>

    @if (session('integration_status'))
        <div role="status" class="flex items-center gap-2.5 rounded-2xl bg-success/[0.07] px-4 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="!size-4.5 shrink-0" />
            <span>{{ session('integration_status') }}</span>
        </div>
    @endif

    @if (session('integration_error'))
        <div role="alert" class="flex items-start gap-2.5 rounded-2xl bg-error/[0.07] px-4 py-3 text-sm leading-7 text-error">
            <x-icon name="lucide.triangle-alert" class="mt-1 !size-4.5 shrink-0" />
            <span>{{ session('integration_error') }}</span>
        </div>
    @endif

    @if ($cloudflareEnabled)
    <section
        x-data="{ detailsOpen: false }"
        class="overflow-hidden rounded-3xl border border-base-300/80 bg-base-100"
    >
        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3.5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <x-icon name="lucide.cloud" class="!size-5 stroke-[1.8]" />
                    </span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold">Cloudflare</h2>

                            @if ($cloudflareConnected)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/[0.08] px-2 py-1 text-[11px] font-medium text-success">
                                    <span class="size-1.5 rounded-full bg-success"></span>
                                    متصل
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-base-200/70 px-2 py-1 text-[11px] font-medium text-base-content/45">
                                    <span class="size-1.5 rounded-full bg-base-content/25"></span>
                                    متصل نیست
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/50">
                            دامنه‌ها و رکوردهای DNS حساب Cloudflare را از داخل Coreflare مدیریت کنید.
                        </p>

                        @if ($cloudflareConnectedAt)
                            <div class="mt-3 inline-flex items-center gap-1.5 text-xs text-base-content/40">
                                <x-icon name="lucide.clock-3" class="!size-3.5" />
                                متصل‌شده {{ $cloudflareConnectedAt->diffForHumans() }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if (! $cloudflareConfigured)
                        <span class="rounded-xl bg-warning/10 px-3 py-2 text-xs font-medium text-warning">
                            نیازمند پیکربندی
                        </span>
                    @elseif (! $cloudflareConnected)
                        <a
                            href="{{ route('panel.integrations.cloudflare.redirect') }}"
                            x-data="{ loading: false }"
                            @click="loading = true"
                            class="btn btn-primary btn-sm rounded-xl px-4"
                            :class="loading && 'pointer-events-none opacity-70'"
                        >
                            <span x-show="! loading" class="inline-flex items-center gap-2">
                                <x-icon name="lucide.link" class="!size-4" />
                                اتصال به Cloudflare
                            </span>
                            <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                                <span class="loading loading-spinner loading-xs"></span>
                                در حال انتقال
                            </span>
                        </a>
                    @elseif (! $cloudflareReadConfigured || ! $cloudflareReadReady)
                        <a
                            href="{{ route('panel.integrations.cloudflare.redirect') }}"
                            x-data="{ loading: false }"
                            @click="loading = true"
                            class="btn btn-primary btn-sm rounded-xl px-4"
                            :class="loading && 'pointer-events-none opacity-70'"
                        >
                            <span x-show="! loading">تکمیل دسترسی Cloudflare</span>
                            <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                                <span class="loading loading-spinner loading-xs"></span>
                                در حال انتقال
                            </span>
                        </a>
                    @else
                        <a
                            href="{{ route('panel.integrations.cloudflare.overview') }}"
                            wire:navigate
                            class="btn btn-primary btn-sm rounded-xl px-4"
                        >
                            <x-icon name="lucide.arrow-left" class="!size-4" />
                            ورود به Cloudflare
                        </a>
                    @endif
                </div>
            </div>

            @if ($cloudflareConnected)
                <div class="mt-5 border-t border-base-300/60 pt-4">
                    <button
                        type="button"
                        @click="detailsOpen = ! detailsOpen"
                        class="inline-flex items-center gap-2 rounded-lg text-xs font-medium text-base-content/45 transition hover:text-base-content/70"
                        :aria-expanded="detailsOpen"
                    >
                        <x-icon name="lucide.settings-2" class="!size-3.5" />
                        جزئیات اتصال
                        <x-icon
                            name="lucide.chevron-down"
                            class="!size-3.5 transition-transform duration-200"
                            x-bind:class="detailsOpen && 'rotate-180'"
                        />
                    </button>

                    <div
                        x-cloak
                        x-show="detailsOpen"
                        x-transition.opacity.duration.150ms
                        class="mt-4 rounded-2xl bg-base-200/35 p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-base-100 px-3 py-3">
                                <div class="text-[10px] text-base-content/40">مشاهده اطلاعات</div>
                                <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $cloudflareReadReady ? 'text-success' : 'text-warning' }}">
                                    <span class="size-1.5 rounded-full {{ $cloudflareReadReady ? 'bg-success' : 'bg-warning' }}"></span>
                                    {{ $cloudflareReadReady ? 'آماده' : 'نیازمند دسترسی' }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-100 px-3 py-3">
                                <div class="text-[10px] text-base-content/40">مدیریت DNS</div>
                                <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $cloudflareDnsWriteReady ? 'text-success' : 'text-base-content/45' }}">
                                    <span class="size-1.5 rounded-full {{ $cloudflareDnsWriteReady ? 'bg-success' : 'bg-base-content/25' }}"></span>
                                    {{ $cloudflareDnsWriteReady ? 'آماده' : 'غیرفعال' }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-100 px-3 py-3">
                                <div class="text-[10px] text-base-content/40">مدیریت دامنه</div>
                                <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $cloudflareZoneManagementReady ? 'text-success' : 'text-base-content/45' }}">
                                    <span class="size-1.5 rounded-full {{ $cloudflareZoneManagementReady ? 'bg-success' : 'bg-base-content/25' }}"></span>
                                    {{ $cloudflareZoneManagementReady ? 'آماده' : 'غیرفعال' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if (! $cloudflareDnsWriteConfigured || ! $cloudflareDnsWriteReady || ! $cloudflareZoneManagementConfigured || ! $cloudflareZoneManagementReady)
                                <a
                                    href="{{ route('panel.integrations.cloudflare.redirect') }}"
                                    x-data="{ loading: false }"
                                    @click="loading = true"
                                    class="btn btn-outline btn-sm rounded-xl"
                                    :class="loading && 'pointer-events-none opacity-70'"
                                >
                                    <span x-show="! loading">به‌روزرسانی مجوزهای Cloudflare</span>
                                    <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        در حال انتقال
                                    </span>
                                </a>
                            @else
                                <a
                                    href="{{ route('panel.integrations.cloudflare.redirect') }}"
                                    x-data="{ loading: false }"
                                    @click="loading = true"
                                    class="btn btn-ghost btn-sm rounded-xl"
                                    :class="loading && 'pointer-events-none opacity-70'"
                                >
                                    <span x-show="! loading">اتصال مجدد Cloudflare</span>
                                    <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        در حال انتقال
                                    </span>
                                </a>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('panel.integrations.cloudflare.disconnect') }}"
                                x-data="{ submitting: false }"
                                @submit="if (! confirm('اتصال Cloudflare قطع شود؟')) { $event.preventDefault(); return; } submitting = true"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-ghost btn-sm rounded-xl text-error"
                                    :disabled="submitting"
                                >
                                    <span x-show="! submitting">قطع اتصال Cloudflare</span>
                                    <span x-cloak x-show="submitting" class="inline-flex items-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        در حال قطع اتصال
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if (! $cloudflareConfigured)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.settings-2" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>اتصال Cloudflare هنوز در محیط Coreflare پیکربندی نشده است.</span>
                </div>
            @elseif (! $cloudflareReadConfigured)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.shield-alert" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>پیکربندی فعلی مجوزهای لازم برای مشاهده اطلاعات Cloudflare را ندارد.</span>
                </div>
            @elseif (! $cloudflareDnsWriteConfigured || ! $cloudflareZoneManagementConfigured)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.shield-alert" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>برای مدیریت کامل دامنه‌ها و DNS، مجوزهای مدیریتی Cloudflare باید در پیکربندی فعال شوند.</span>
                </div>
            @elseif ($cloudflareConnected && (! $cloudflareReadReady || ! $cloudflareDnsWriteReady || ! $cloudflareZoneManagementReady))
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.shield-alert" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>اتصال فعلی همه دسترسی‌های موردنیاز را ندارد. از «جزئیات اتصال» مجوزهای Cloudflare را به‌روزرسانی کنید.</span>
                </div>
            @endif
        </div>
    </section>
    @endif

    <section class="overflow-hidden rounded-3xl border border-base-300/80 bg-base-100">
        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3.5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-info/10 text-info">
                        <x-icon name="lucide.send" class="!size-5 stroke-[1.8]" />
                    </span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold">Telegram</h2>

                            @if ($telegramConnected)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/[0.08] px-2 py-1 text-[11px] font-medium text-success">
                                    <span class="size-1.5 rounded-full bg-success"></span>
                                    متصل
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-base-200/70 px-2 py-1 text-[11px] font-medium text-base-content/45">
                                    <span class="size-1.5 rounded-full bg-base-content/25"></span>
                                    متصل نیست
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/50">
                            اعلان‌های مهم سرورها، پشتیبانی و حساب کاربری را مستقیم در Telegram دریافت کنید.
                        </p>

                        @if ($telegramConnected)
                            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-base-content/40">
                                @if ($telegramUsername)
                                    <span dir="ltr">{{ '@'.$telegramUsername }}</span>
                                @endif

                                @if ($telegramConnectedAt)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="lucide.clock-3" class="!size-3.5" />
                                        متصل‌شده {{ $telegramConnectedAt->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="shrink-0">
                    @if (! $telegramConfigured)
                        <span class="rounded-xl bg-warning/10 px-3 py-2 text-xs font-medium text-warning">
                            نیازمند پیکربندی
                        </span>
                    @else
                        <a
                            href="{{ route('panel.integrations.telegram.overview') }}"
                            wire:navigate
                            class="btn btn-primary btn-sm rounded-xl px-4"
                        >
                            <x-icon name="lucide.arrow-left" class="!size-4" />
                            {{ $telegramConnected ? 'مدیریت Telegram' : 'راه‌اندازی Telegram' }}
                        </a>
                    @endif
                </div>
            </div>

            @if (! $telegramConfigured)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.settings-2" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>Telegram هنوز در محیط Coreflare پیکربندی نشده است.</span>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-3xl border border-dashed border-base-300/80 bg-base-100/60 p-5 sm:p-6">
        <div class="flex items-start gap-3.5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-200/70 text-base-content/45">
                <x-icon name="lucide.github" class="!size-4.5" />
            </span>

            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold">GitHub</h2>
                    <span class="rounded-lg bg-base-200 px-2 py-1 text-[10px] font-medium text-base-content/40">به‌زودی</span>
                </div>
                <p class="mt-1 text-xs leading-6 text-base-content/45">
                    اتصال مخزن‌ها و جریان استقرار GitHub در ادامه به بخش یکپارچه‌سازی‌ها اضافه می‌شود.
                </p>
            </div>
        </div>
    </section>
</div>
