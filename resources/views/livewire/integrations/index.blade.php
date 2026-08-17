<div class="mx-auto w-full max-w-5xl space-y-6">
    <header class="max-w-2xl">
        <div class="flex items-center gap-2 text-sm font-medium text-primary">
            <span class="flex size-8 items-center justify-center rounded-xl bg-primary/10">
                <x-icon name="lucide.link-2" class="!size-4 stroke-[1.8]" />
            </span>
            اتصال‌ها
        </div>

        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
            سرویس‌های خارجی را به Coreflare متصل کنید
        </h1>

        <p class="mt-2 text-sm leading-7 text-base-content/50">
            اتصال سرویس‌ها فقط مجوزهای لازم را دریافت می‌کند. مدیریت دامنه و عملیات هر سرویس در بخش مرتبط خودش انجام می‌شود.
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

    <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3.5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-icon name="lucide.cloud" class="!size-5 stroke-[1.8]" />
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold">Cloudflare</h2>

                        @if ($cloudflareConnected)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-success">
                                <span class="size-1.5 rounded-full bg-success"></span>
                                متصل
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-base-content/40">
                                <span class="size-1.5 rounded-full bg-base-content/25"></span>
                                متصل نیست
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/45">
                        اتصال Cloudflare پایه مدیریت خودکار DNS و دامنه‌ها در Coreflare است. در این فاز فقط دسترسی خواندن اطلاعات حساب درخواست می‌شود.
                    </p>

                    @if ($cloudflareConnected)
                        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-base-content/45">
                            @if ($cloudflareConnectedAt)
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide.clock-3" class="!size-3.5" />
                                    اتصال {{ $cloudflareConnectedAt->diffForHumans() }}
                                </span>
                            @endif

                            @if (in_array('account.read', $cloudflareScopes, true))
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="lucide.shield-check" class="!size-3.5" />
                                    دسترسی خواندن حساب
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @if (! $cloudflareConfigured)
                    <span class="rounded-xl bg-warning/10 px-3 py-2 text-xs font-medium text-warning">
                        نیازمند پیکربندی
                    </span>
                @elseif (! $cloudflareConnected)
                    <a
                        href="{{ route('panel.integrations.cloudflare.redirect') }}"
                        class="btn btn-primary btn-sm rounded-xl px-4"
                    >
                        <x-icon name="lucide.link" class="!size-4" />
                        اتصال Cloudflare
                    </a>
                @else
                    <a
                        href="{{ route('panel.integrations.cloudflare.redirect') }}"
                        class="btn btn-ghost btn-sm rounded-xl"
                    >
                        اتصال مجدد
                    </a>

                    <form
                        method="POST"
                        action="{{ route('panel.integrations.cloudflare.disconnect') }}"
                        onsubmit="return confirm('اتصال Cloudflare قطع شود؟');"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-ghost btn-sm rounded-xl text-error"
                        >
                            قطع اتصال
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (! $cloudflareConfigured)
            <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                <x-icon name="lucide.settings-2" class="mt-1 !size-3.5 shrink-0 text-warning" />
                <span>
                    Client ID و Client Secret مربوط به Cloudflare OAuth باید در محیط اجرا تنظیم شوند تا اتصال برای کاربران فعال شود.
                </span>
            </div>
        @endif
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
                    اتصال Repository و Deploy از GitHub در همین ساختار Integrations اضافه خواهد شد.
                </p>
            </div>
        </div>
    </section>
</div>
