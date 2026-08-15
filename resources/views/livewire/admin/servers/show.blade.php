<div class="space-y-5">
    <x-admin.page-header
        :title="$server->name ?: 'سرور بدون نام'"
        description="اطلاعات مدیریتی، دسترسی پشتیبانی کنترل‌شده و تاریخچه عملیات حساس سرور."
        icon="lucide.server-cog"
    >
        <x-slot:actions>
            <x-button
                label="بازگشت به سرورها"
                icon="lucide.arrow-right"
                :link="route('admin.servers.index')"
                wire:navigate
                class="btn-ghost btn-sm"
            />
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">اتصال و مالکیت</h2>
                <x-admin.status-badge :status="$server->status" />
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-base-content/45">شناسه</dt>
                    <dd class="mt-1 font-mono text-sm">#{{ $server->id }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">مالک</dt>
                    <dd class="mt-1 text-sm">
                        <a
                            class="link link-hover"
                            href="{{ route('admin.users.show', $server->user) }}"
                            wire:navigate
                        >
                            {{ $server->user?->name ?: $server->user?->phone }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Host</dt>
                    <dd class="mt-1 font-mono text-sm" dir="ltr">
                        {{ $server->host }}:{{ $server->port }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Username</dt>
                    <dd class="mt-1 font-mono text-sm" dir="ltr">
                        {{ $server->username }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Authentication</dt>
                    <dd class="mt-1 text-sm">
                        {{ $server->authentication_type->label() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">ایجاد</dt>
                    <dd class="mt-1 text-sm">
                        {{ $server->created_at?->format('Y-m-d H:i') }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5">
            <h2 class="text-sm font-semibold">Cloud lifecycle</h2>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-base-content/45">Provider</dt>
                    <dd class="mt-1 text-sm">{{ $server->cloud_provider ?: 'Manual' }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Provider Server ID</dt>
                    <dd class="mt-1 font-mono text-sm">{{ $server->cloud_server_id ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Region</dt>
                    <dd class="mt-1 text-sm">{{ $server->cloud_region ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Provisioned</dt>
                    <dd class="mt-1 text-sm">{{ $server->provisioned_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Expires</dt>
                    <dd class="mt-1 text-sm">{{ $server->expires_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-base-content/45">Terminated</dt>
                    <dd class="mt-1 text-sm">{{ $server->terminated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </section>
    </div>

    <section
        class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
        x-data="{
            credential: null,
            revealLoading: false,
            revealError: null,
            supportVerifyLoading: false,
            supportVerifyError: null,
            clearTimer: null,
            async confirmSupportAccess() {
                this.supportVerifyLoading = true;
                this.supportVerifyError = null;
                this.credential = null;

                try {
                    await $wire.prepareSupportPasskeyVerification();

                    if (! window.CoreflarePasskeys?.isSupported()) {
                        throw new Error('Passkey is not supported.');
                    }

                    await window.CoreflarePasskeys.verify({
                        optionsUrl: @js(route('admin.servers.support.passkey.options', $server)),
                        verifyUrl: @js(route('admin.servers.support.passkey.verify', $server)),
                    });

                    await $wire.set('supportAccessConfirmed', true);
                } catch (error) {
                    this.supportVerifyError = window.CoreflarePasskeys?.messageFor(error)
                        ?? 'تأیید Passkey انجام نشد. دوباره تلاش کنید.';
                } finally {
                    this.supportVerifyLoading = false;
                }
            },
            async revealCredential() {
                this.revealLoading = true;
                this.revealError = null;

                try {
                    const response = await fetch(
                        @js(route('admin.servers.support.reveal-credential', $server)),
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                            body: JSON.stringify({
                                reason: this.$refs.supportReason.value,
                            }),
                        },
                    );

                    if (response.status === 403) {
                        this.credential = null;
                        this.revealError = 'مجوز دسترسی حساس منقضی شده است. دوباره با Passkey تأیید کنید.';
                        $wire.set('supportAccessConfirmed', false);
                        return;
                    }

                    const payload = await response.json();

                    if (! response.ok) {
                        this.credential = null;
                        this.revealError = payload?.errors?.reason?.[0]
                            ?? 'نمایش اطلاعات اتصال انجام نشد.';
                        return;
                    }

                    this.credential = payload.credential;

                    clearTimeout(this.clearTimer);
                    this.clearTimer = setTimeout(() => {
                        this.credential = null;
                    }, 30000);
                } catch (error) {
                    this.credential = null;
                    this.revealError = 'ارتباط با سرور برنامه برای دریافت اطلاعات حساس برقرار نشد.';
                } finally {
                    this.revealLoading = false;
                }
            },
            clearCredential() {
                clearTimeout(this.clearTimer);
                this.credential = null;
            },
        }"
    >
        <div class="border-b border-base-300 p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                        <x-icon name="lucide.shield-alert" class="!size-5 stroke-[1.7]" />
                    </span>

                    <div>
                        <h2 class="text-base font-semibold">دسترسی پشتیبانی</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-7 text-base-content/55">
                            عملیات پشتیبانی این بخش با دلیل دسترسی ثبت می‌شوند. نمایش رمز عبور فقط پس از تأیید مجدد هویت مدیر و برای همین سرور امکان‌پذیر است.
                        </p>
                    </div>
                </div>

                @if ($server->host && $server->username)
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm self-start"
                        x-on:click="navigator.clipboard.writeText(@js('ssh -p '.$server->port.' '.$server->username.'@'.$server->host))"
                    >
                        <x-icon name="lucide.copy" class="!size-4" />
                        کپی دستور SSH
                    </button>
                @endif
            </div>
        </div>

        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.72fr)]">
            <div class="space-y-5 p-5 sm:p-6 xl:border-l xl:border-base-300">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-base-200/55 p-3.5">
                        <div class="text-xs text-base-content/45">Host</div>
                        <div class="mt-1 truncate font-mono text-sm" dir="ltr">{{ $server->host ?: '—' }}</div>
                    </div>

                    <div class="rounded-xl bg-base-200/55 p-3.5">
                        <div class="text-xs text-base-content/45">Port</div>
                        <div class="mt-1 font-mono text-sm" dir="ltr">{{ $server->port }}</div>
                    </div>

                    <div class="rounded-xl bg-base-200/55 p-3.5">
                        <div class="text-xs text-base-content/45">Username</div>
                        <div class="mt-1 truncate font-mono text-sm" dir="ltr">{{ $server->username ?: '—' }}</div>
                    </div>
                </div>

                <label class="form-control w-full">
                    <span class="mb-2 text-sm font-medium">دلیل دسترسی</span>
                    <textarea
                        x-ref="supportReason"
                        wire:model.live.debounce.250ms="supportReason"
                        class="textarea textarea-bordered min-h-24 w-full rounded-xl leading-7"
                        maxlength="500"
                        placeholder="مثلاً: بررسی خطای نصب n8n گزارش‌شده توسط کاربر"
                    ></textarea>
                    <span class="mt-1.5 text-xs text-base-content/45">
                        این متن در Audit Log ذخیره می‌شود.
                    </span>
                    @error('supportReason')
                        <span class="mt-1.5 text-xs text-error">{{ $message }}</span>
                    @enderror
                </label>

                <div class="rounded-2xl border border-base-300 bg-base-200/25 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-sm font-semibold">
                                <x-icon name="lucide.plug-zap" class="!size-4 text-primary" />
                                تست اتصال SSH
                            </div>
                            <p class="mt-1 text-xs leading-6 text-base-content/50">
                                فقط اتصال SSH را بررسی می‌کند و هیچ فرمان مدیریتی روی سرور اجرا نمی‌شود.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="testSupportConnection"
                            wire:loading.attr="disabled"
                            wire:target="testSupportConnection"
                            class="btn btn-primary btn-sm shrink-0"
                        >
                            <span wire:loading.remove wire:target="testSupportConnection">تست اتصال</span>
                            <span wire:loading wire:target="testSupportConnection" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>

                    @if ($connectionTestMessage !== null)
                        <div
                            @class([
                                'mt-3 rounded-xl border px-3.5 py-2.5 text-sm',
                                'border-success/20 bg-success/5 text-success' => $connectionTestPassed === true,
                                'border-error/20 bg-error/5 text-error' => $connectionTestPassed === false,
                            ])
                        >
                            {{ $connectionTestMessage }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-4 bg-warning/[0.025] p-5 sm:p-6">
                <div>
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="lucide.key-round" class="!size-4 text-warning" />
                        دسترسی حساس
                    </div>
                    <p class="mt-1 text-xs leading-6 text-base-content/50">
                        برای Reveal رمز عبور، مدیر با Passkey دوباره تأیید می‌شود. مجوز پنج دقیقه اعتبار دارد و فقط به همین سرور محدود است.
                    </p>
                </div>

                @if ($server->authentication_type === \App\Domain\Server\Enums\AuthenticationType::Password)
                    @if (! $supportAccessConfirmed)
                        <button
                            type="button"
                            class="btn btn-warning btn-sm w-full"
                            x-on:click="confirmSupportAccess()"
                            x-bind:disabled="supportVerifyLoading"
                        >
                            <span x-show="! supportVerifyLoading" class="inline-flex items-center gap-2">
                                <x-icon name="lucide.fingerprint" class="!size-4" />
                                تأیید با Passkey
                            </span>
                            <span x-show="supportVerifyLoading" class="loading loading-spinner loading-xs"></span>
                        </button>

                        <div
                            x-cloak
                            x-show="supportVerifyError"
                            class="rounded-xl border border-error/20 bg-error/5 p-3 text-xs leading-6 text-error"
                            x-text="supportVerifyError"
                        ></div>

                        <div class="rounded-xl border border-warning/15 bg-warning/5 p-3 text-xs leading-6 text-base-content/55">
                            Passkey باید روی حساب مدیر ثبت شده باشد. Challenge این مرحله یک‌بارمصرف و مخصوص همین سرور است.
                        </div>
                    @else
                        <div class="flex items-start gap-2 rounded-xl border border-success/20 bg-success/5 p-3.5 text-sm text-success">
                            <x-icon name="lucide.shield-check" class="mt-0.5 !size-4 shrink-0" />
                            <span>هویت مدیر برای دسترسی حساس این سرور با Passkey تأیید شده است.</span>
                        </div>

                        <button
                            type="button"
                            x-on:click="revealCredential()"
                            x-bind:disabled="revealLoading"
                            class="btn btn-warning btn-sm w-full"
                        >
                            <span x-show="! revealLoading">نمایش موقت رمز عبور</span>
                            <span x-show="revealLoading" class="loading loading-spinner loading-xs"></span>
                        </button>

                        <div
                            x-cloak
                            x-show="revealError"
                            class="rounded-xl border border-error/20 bg-error/5 p-3 text-xs leading-6 text-error"
                            x-text="revealError"
                        ></div>

                        <div
                            x-cloak
                            x-show="credential !== null"
                            class="space-y-3 rounded-xl border border-warning/25 bg-base-100 p-4 shadow-sm"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-base-content/55">Password</span>
                                <span class="badge badge-warning badge-sm">۳۰ ثانیه</span>
                            </div>

                            <div class="break-all rounded-lg bg-base-200 px-3 py-2.5 font-mono text-sm" dir="ltr" x-text="credential"></div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs"
                                    x-on:click="navigator.clipboard.writeText(credential)"
                                >
                                    <x-icon name="lucide.copy" class="!size-3.5" />
                                    کپی رمز
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs"
                                    x-on:click="clearCredential()"
                                >
                                    <x-icon name="lucide.eye-off" class="!size-3.5" />
                                    مخفی‌کردن
                                </button>
                            </div>

                            <p class="text-[11px] leading-5 text-base-content/45">
                                رمز در Livewire state یا Audit Log ذخیره نمی‌شود و پس از ۳۰ ثانیه از این نما پاک خواهد شد.
                            </p>
                        </div>
                    @endif
                @else
                    <div class="rounded-xl border border-base-300 bg-base-200/40 p-4 text-sm leading-7 text-base-content/60">
                        این سرور از Password Authentication استفاده نمی‌کند؛ بنابراین رمز قابل Reveal وجود ندارد.
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="flex items-center justify-between gap-3 border-b border-base-300 p-5">
            <div>
                <h2 class="text-sm font-semibold">تاریخچه دسترسی پشتیبانی</h2>
                <p class="mt-1 text-xs text-base-content/45">۱۰ عملیات حساس اخیر این سرور</p>
            </div>
            <x-icon name="lucide.history" class="!size-4 text-base-content/35" />
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>عملیات</th>
                    <th>مدیر</th>
                    <th>دلیل</th>
                    <th>نتیجه</th>
                    <th>زمان</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($supportAccessLogs as $accessLog)
                    <tr>
                        <td class="text-sm">
                            {{ match ($accessLog->action) {
                                \App\Domain\Server\Enums\SupportAccessAction::SshConnectionTest => 'تست اتصال SSH',
                                \App\Domain\Server\Enums\SupportAccessAction::PasskeyConfirmed => 'تأیید Passkey',
                                \App\Domain\Server\Enums\SupportAccessAction::CredentialRevealed => 'نمایش Credential',
                            } }}
                        </td>
                        <td class="text-sm">{{ $accessLog->adminUser?->name ?: $accessLog->adminUser?->phone }}</td>
                        <td class="max-w-xs truncate text-sm text-base-content/65" title="{{ $accessLog->reason }}">{{ $accessLog->reason }}</td>
                        <td>
                            <span @class([
                                'badge badge-sm',
                                'badge-success' => $accessLog->successful,
                                'badge-error' => ! $accessLog->successful,
                            ])>
                                {{ $accessLog->successful ? 'موفق' : 'ناموفق' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-xs text-base-content/50" dir="ltr">{{ $accessLog->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-base-content/45">
                            هنوز دسترسی پشتیبانی ثبت نشده است.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-5">
            <h2 class="text-sm font-semibold">سفارش‌های مرتبط</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>سفارش</th>
                    <th>نوع</th>
                    <th>مبلغ</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td class="font-mono text-xs">#{{ $order->id }}</td>
                        <td>{{ $order->type->value }}</td>
                        <td>{{ number_format($order->final_amount) }} {{ $order->currency }}</td>
                        <td><x-admin.status-badge :status="$order->status" /></td>
                        <td class="text-left">
                            <x-button
                                label="جزئیات"
                                :link="route('admin.orders.show', $order)"
                                wire:navigate
                                class="btn-ghost btn-xs"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-base-content/45">
                            سفارش مرتبطی وجود ندارد.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
