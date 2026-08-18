<div
    @if (! $connection) wire:poll.5s @endif
    dir="rtl"
    class="mx-auto w-full max-w-5xl space-y-6"
>
    <header class="max-w-2xl">
        <a
            href="{{ route('panel.integrations.index') }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 text-xs font-medium text-base-content/45 transition hover:text-base-content/70"
        >
            <x-icon name="lucide.arrow-right" class="!size-3.5" />
            یکپارچه‌سازی‌ها
        </a>

        <div class="mt-4 flex items-center gap-2 text-sm font-medium text-primary">
            <span class="flex size-8 items-center justify-center rounded-xl bg-primary/10">
                <x-icon name="lucide.send" class="!size-4 stroke-[1.8]" />
            </span>
            Telegram
        </div>

        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
            اعلان‌های مهم را در Telegram دریافت کنید
        </h1>

        <p class="mt-2 text-sm leading-7 text-base-content/50">
            حساب Telegram خود را یک‌بار متصل کنید و مشخص کنید کدام گروه از اعلان‌ها برای شما ارسال شود.
        </p>
    </header>

    @if ($statusMessage)
        <div role="status" class="flex items-center gap-2.5 rounded-2xl bg-success/[0.07] px-4 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="!size-4.5 shrink-0" />
            <span>{{ $statusMessage }}</span>
        </div>
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
                            <h2 class="text-base font-semibold">اتصال Telegram</h2>

                            @if ($connection)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/[0.08] px-2 py-1 text-[11px] font-medium text-success">
                                    <span class="size-1.5 rounded-full bg-success"></span>
                                    متصل
                                </span>
                            @elseif ($linkPending)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/[0.08] px-2 py-1 text-[11px] font-medium text-warning">
                                    <span class="size-1.5 rounded-full bg-warning"></span>
                                    در انتظار تأیید
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-base-200/70 px-2 py-1 text-[11px] font-medium text-base-content/45">
                                    <span class="size-1.5 rounded-full bg-base-content/25"></span>
                                    متصل نیست
                                </span>
                            @endif
                        </div>

                        @if ($connection)
                            <p class="mt-1 text-sm leading-7 text-base-content/50">
                                اعلان‌های انتخاب‌شده از این پس به حساب متصل‌شده ارسال می‌شوند.
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-base-content/45">
                                @if ($connection->first_name)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="lucide.user" class="!size-3.5" />
                                        {{ $connection->first_name }}
                                    </span>
                                @endif

                                @if ($connection->username)
                                    <span dir="ltr" class="inline-flex items-center gap-1.5">
                                        {{ '@'.$connection->username }}
                                    </span>
                                @endif

                                @if ($connection->connected_at)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="lucide.clock-3" class="!size-3.5" />
                                        {{ $connection->connected_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <p class="mt-1 max-w-xl text-sm leading-7 text-base-content/50">
                                با باز کردن Bot در Telegram و زدن Start، اتصال به‌صورت خودکار تکمیل می‌شود. هیچ رمز یا کد دستی لازم نیست.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="shrink-0">
                    @if (! $telegramConfigured)
                        <span class="rounded-xl bg-warning/10 px-3 py-2 text-xs font-medium text-warning">
                            نیازمند پیکربندی
                        </span>
                    @elseif ($connection)
                        <button
                            type="button"
                            x-data="{ submitting: false }"
                            @click="if (confirm('اتصال Telegram قطع شود؟ ارسال اعلان‌های Telegram متوقف می‌شود، اما اعلان‌های داخل پنل باقی می‌مانند.')) { submitting = true; $wire.disconnect().finally(() => submitting = false) }"
                            :disabled="submitting"
                            class="btn btn-ghost btn-sm rounded-xl text-error"
                        >
                            <span x-show="! submitting">قطع اتصال</span>
                            <span x-cloak x-show="submitting" class="inline-flex items-center gap-2">
                                <span class="loading loading-spinner loading-xs"></span>
                                در حال قطع اتصال
                            </span>
                        </button>
                    @else
                        <form
                            method="POST"
                            action="{{ route('panel.integrations.telegram.connect') }}"
                            target="_blank"
                            x-data="{ opening: false }"
                            @submit="opening = true; setTimeout(() => opening = false, 1800)"
                        >
                            @csrf

                            <button
                                type="submit"
                                :disabled="opening"
                                class="btn btn-primary btn-sm rounded-xl px-4"
                            >
                                <span x-show="! opening" class="inline-flex items-center gap-2">
                                    <x-icon name="lucide.external-link" class="!size-4" />
                                    {{ $linkPending ? 'دریافت لینک جدید' : 'اتصال Telegram' }}
                                </span>
                                <span x-cloak x-show="opening" class="inline-flex items-center gap-2">
                                    <span class="loading loading-spinner loading-xs"></span>
                                    در حال باز کردن
                                </span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if (! $telegramConfigured)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-warning/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <x-icon name="lucide.settings-2" class="mt-1 !size-3.5 shrink-0 text-warning" />
                    <span>Telegram هنوز در این محیط Coreflare پیکربندی نشده است.</span>
                </div>
            @elseif ($linkPending && ! $connection)
                <div class="mt-5 flex items-start gap-2.5 rounded-2xl bg-info/[0.07] px-4 py-3 text-xs leading-6 text-base-content/60">
                    <span class="loading loading-dots loading-xs mt-1 shrink-0 text-info"></span>
                    <span>در انتظار تأیید در Telegram هستیم. پس از زدن Start، این صفحه خودکار به‌روزرسانی می‌شود.</span>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-3xl border border-base-300/80 bg-base-100 p-5 sm:p-6">
        <div class="flex items-start gap-3.5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <x-icon name="lucide.sliders-horizontal" class="!size-4.5" />
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold">انتخاب اعلان‌ها</h2>
                <p class="mt-1 text-xs leading-6 text-base-content/45">
                    این تنظیمات فقط ارسال به Telegram را کنترل می‌کنند؛ اعلان‌های داخل پنل همیشه مستقل باقی می‌مانند.
                </p>
            </div>
        </div>

        @if ($connection && $telegramConfigured)
            <div class="mt-5 divide-y divide-base-300/60 overflow-hidden rounded-2xl border border-base-300/60">
                @foreach ([
                    'servers' => ['سرورها و سرویس‌ها', 'پایان سرویس، حذف VPS و رویدادهای مهم زیرساخت', 'lucide.server'],
                    'support' => ['پشتیبانی', 'پاسخ‌های جدید تیم پشتیبانی به درخواست‌های شما', 'lucide.messages-square'],
                    'account' => ['حساب کاربری', 'یادآوری‌های ضروری مربوط به پروفایل و حساب', 'lucide.user-round'],
                ] as $topic => [$label, $description, $icon])
                    <div class="flex items-center gap-4 px-4 py-4 sm:px-5">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200/60 text-base-content/55">
                            <x-icon :name="$icon" class="!size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium">{{ $label }}</div>
                            <p class="mt-0.5 text-xs leading-6 text-base-content/45">{{ $description }}</p>
                        </div>

                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ ($preferences[$topic] ?? true) ? 'true' : 'false' }}"
                            wire:click="togglePreference('{{ $topic }}')"
                            wire:loading.attr="disabled"
                            wire:target="togglePreference"
                            @class([
                                'relative h-6 w-11 shrink-0 rounded-full transition-colors duration-200 disabled:opacity-60',
                                'bg-primary' => $preferences[$topic] ?? true,
                                'bg-base-300' => ! ($preferences[$topic] ?? true),
                            ])
                        >
                            <span
                                @class([
                                    'absolute top-0.5 size-5 rounded-full bg-white shadow-sm transition-all duration-200',
                                    'left-0.5' => $preferences[$topic] ?? true,
                                    'left-[1.375rem]' => ! ($preferences[$topic] ?? true),
                                ])
                            ></span>
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-5 rounded-2xl bg-base-200/35 px-4 py-4 text-xs leading-6 text-base-content/45">
                پس از اتصال Telegram، انتخاب نوع اعلان‌ها از همین بخش فعال می‌شود.
            </div>
        @endif
    </section>
</div>
