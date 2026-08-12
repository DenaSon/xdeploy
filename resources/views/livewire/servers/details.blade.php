<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    @php
        $authenticationLabel = match ($server->authentication_type) {
            \App\Domain\Server\Enums\AuthenticationType::Password => 'رمز عبور',
            \App\Domain\Server\Enums\AuthenticationType::SSHKey => 'کلید SSH',
            \App\Domain\Server\Enums\AuthenticationType::Agent => 'SSH Agent',
        };

        $providerLabel = match ($server->cloud_provider) {
            'arvan', 'arvancloud' => 'ArvanCloud',
            null => '—',
            default => (string) $server->cloud_provider,
        };

        $formatDate = static fn ($value): string => $value !== null
            ? \App\Support\Date\JalaliDateFormatter::dateTime($value, ' - ')
            : '—';
    @endphp

    <div
        class="space-y-4"
        data-reveal-url="{{ route('panel.servers.credential.reveal', ['server' => $server]) }}"
        data-csrf-token="{{ csrf_token() }}"
        x-data="{
            password: null,
            passwordLoading: false,
            passwordError: null,
            passwordTimer: null,

            async copy(value) {
                if (!value) return;

                try {
                    await navigator.clipboard.writeText(value);
                } catch (error) {
                    // Clipboard access can be unavailable outside secure contexts.
                }
            },

            async revealPassword() {
                if (this.password !== null) {
                    this.hidePassword();
                    return;
                }

                this.passwordLoading = true;
                this.passwordError = null;

                try {
                    const response = await fetch(
                        this.$root.dataset.revealUrl,
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.$root.dataset.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    );

                    if (!response.ok) {
                        throw new Error('credential_reveal_failed');
                    }

                    const data = await response.json();

                    if (
                        typeof data.credential !== 'string'
                        || data.credential.length === 0
                    ) {
                        throw new Error('credential_missing');
                    }

                    this.password = data.credential;

                    window.clearTimeout(this.passwordTimer);
                    this.passwordTimer = window.setTimeout(
                        () => this.hidePassword(),
                        30000,
                    );
                } catch (error) {
                    this.password = null;
                    this.passwordError = 'دریافت رمز عبور ناموفق بود. دوباره تلاش کنید.';
                } finally {
                    this.passwordLoading = false;
                }
            },

            hidePassword() {
                this.password = null;
                window.clearTimeout(this.passwordTimer);
                this.passwordTimer = null;
            },

            destroy() {
                this.hidePassword();
            },
        }"
    >
        {{-- Connection information --}}
        <section
            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
        >
            <div
                class="flex items-center gap-3 border-b border-base-300 px-4 py-3.5 sm:px-5"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/55"
                >
                    <x-icon
                        name="lucide.terminal"
                        class="!size-4.5"
                    />
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-base-content">
                        اطلاعات اتصال
                    </h2>

                    <p class="mt-0.5 text-[11px] text-base-content/40">
                        اطلاعات موردنیاز برای اتصال مستقیم SSH به سرور
                    </p>
                </div>
            </div>

            <div class="divide-y divide-base-300">
                {{-- Host --}}
                <div
                    class="flex flex-col gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <div class="flex items-center gap-2.5">
                        <x-icon
                            name="lucide.network"
                            class="!size-4 text-base-content/35"
                        />

                        <span class="text-xs text-base-content/55">
                            آدرس سرور
                        </span>
                    </div>

                    <div class="flex min-w-0 items-center gap-2">
                        <code
                            dir="ltr"
                            class="technical-value min-w-0 truncate text-sm font-medium text-base-content"
                        >{{ $server->host ?: '—' }}</code>

                        @if($server->host)
                            <x-button
                                icon="lucide.copy"
                                data-copy-value="{{ $server->host }}"
                                x-on:click="copy($el.dataset.copyValue)"
                                class="btn-ghost btn-xs rounded-lg"
                                aria-label="کپی آدرس سرور"
                            />
                        @endif
                    </div>
                </div>

                {{-- Port --}}
                <div
                    class="flex flex-col gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <div class="flex items-center gap-2.5">
                        <x-icon
                            name="lucide.plug"
                            class="!size-4 text-base-content/35"
                        />

                        <span class="text-xs text-base-content/55">
                            پورت SSH
                        </span>
                    </div>

                    <code
                        dir="ltr"
                        class="technical-value text-sm font-medium text-base-content"
                    >{{ $server->port }}</code>
                </div>

                {{-- Username --}}
                <div
                    class="flex flex-col gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <div class="flex items-center gap-2.5">
                        <x-icon
                            name="lucide.user-round"
                            class="!size-4 text-base-content/35"
                        />

                        <span class="text-xs text-base-content/55">
                            نام کاربری
                        </span>
                    </div>

                    <div class="flex min-w-0 items-center gap-2">
                        <code
                            dir="ltr"
                            class="technical-value min-w-0 truncate text-sm font-medium text-base-content"
                        >{{ $server->username }}</code>

                        <x-button
                            icon="lucide.copy"
                            data-copy-value="{{ $server->username }}"
                            x-on:click="copy($el.dataset.copyValue)"
                            class="btn-ghost btn-xs rounded-lg"
                            aria-label="کپی نام کاربری"
                        />
                    </div>
                </div>

                {{-- Credential --}}
                <div
                    class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <div class="flex items-center gap-2.5">
                        <x-icon
                            name="lucide.key-round"
                            class="!size-4 text-base-content/35"
                        />

                        <div>
                            <span class="text-xs text-base-content/55">
                                روش احراز هویت
                            </span>

                            <p class="mt-0.5 text-[11px] text-base-content/35">
                                {{ $authenticationLabel }}
                            </p>
                        </div>
                    </div>

                    @if($server->authentication_type === \App\Domain\Server\Enums\AuthenticationType::Password)
                        <div class="min-w-0 sm:max-w-[70%]">
                            <div class="flex items-center justify-end gap-2">
                                <code
                                    dir="ltr"
                                    class="technical-value min-w-0 break-all text-sm font-medium text-base-content"
                                    x-text="password ?? '••••••••••••'"
                                ></code>

                                <x-button
                                    icon="lucide.copy"
                                    x-on:click="copy(password)"
                                    x-bind:disabled="password === null"
                                    class="btn-ghost btn-xs rounded-lg"
                                    aria-label="کپی رمز عبور"
                                />

                                <button
                                    type="button"
                                    x-on:click="revealPassword()"
                                    x-bind:disabled="passwordLoading"
                                    class="btn btn-ghost btn-xs rounded-lg"
                                >
                                    <span
                                        class="loading loading-spinner loading-xs"
                                        x-show="passwordLoading"
                                    ></span>

                                    <x-icon
                                        name="lucide.eye"
                                        class="!size-3.5"
                                        x-show="!passwordLoading && password === null"
                                    />

                                    <x-icon
                                        name="lucide.eye-off"
                                        class="!size-3.5"
                                        x-show="!passwordLoading && password !== null"
                                    />

                                    <span
                                        x-text="password !== null ? 'مخفی کردن' : 'نمایش رمز'"
                                    ></span>
                                </button>
                            </div>

                            <p
                                class="mt-1.5 text-left text-[11px] text-error"
                                x-show="passwordError"
                                x-text="passwordError"
                            ></p>
                        </div>
                    @else
                        <p class="text-xs text-base-content/45">
                            اطلاعات محرمانه این روش احراز هویت در این صفحه نمایش داده نمی‌شود.
                        </p>
                    @endif
                </div>

                {{-- SSH command --}}
                @if($sshCommand !== null)
                    <div class="px-4 py-4 sm:px-5">
                        <div
                            class="flex flex-col gap-3 rounded-xl border border-base-300 bg-base-200/35 px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium text-base-content/45">
                                    دستور اتصال SSH
                                </p>

                                <code
                                    dir="ltr"
                                    class="technical-value mt-1 block overflow-x-auto whitespace-nowrap text-sm text-base-content"
                                >{{ $sshCommand }}</code>
                            </div>

                            <x-button
                                label="کپی"
                                icon="lucide.copy"
                                data-copy-value="{{ $sshCommand }}"
                                x-on:click="copy($el.dataset.copyValue)"
                                class="btn-outline btn-sm shrink-0 rounded-xl"
                            />
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if($server->authentication_type === \App\Domain\Server\Enums\AuthenticationType::Password)
            <div
                role="alert"
                class="alert alert-info alert-soft rounded-2xl"
            >
                <x-icon
                    name="lucide.shield-check"
                    class="!size-4.5 shrink-0"
                />

                <span class="text-xs leading-6">
                    رمز عبور فقط با درخواست شما نمایش داده می‌شود و پس از ۳۰ ثانیه دوباره مخفی خواهد شد. آن را در اختیار افراد دیگر قرار ندهید.
                </span>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {{-- Server metadata --}}
            <section
                class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            >
                <div
                    class="flex items-center gap-3 border-b border-base-300 px-4 py-3.5 sm:px-5"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/55"
                    >
                        <x-icon
                            name="lucide.server-cog"
                            class="!size-4.5"
                        />
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-base-content">
                            مشخصات سرور
                        </h2>

                        <p class="mt-0.5 text-[11px] text-base-content/40">
                            اطلاعات ثبت‌شده در xDeploy
                        </p>
                    </div>
                </div>

                <dl class="divide-y divide-base-300">
                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                        <dt class="text-xs text-base-content/50">نام سرور</dt>
                        <dd
                            dir="ltr"
                            class="technical-value min-w-0 truncate text-sm font-medium text-base-content"
                        >{{ $server->name }}</dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                        <dt class="text-xs text-base-content/50">وضعیت</dt>
                        <dd>
                            <span
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium',
                                    'border-success/20 bg-success/10 text-success' => $server->isActive(),
                                    'border-base-300 bg-base-200 text-base-content/55' => ! $server->isActive(),
                                ])
                            >
                                <span
                                    @class([
                                        'size-1.5 rounded-full',
                                        'bg-success' => $server->isActive(),
                                        'bg-base-content/30' => ! $server->isActive(),
                                    ])
                                ></span>

                                {{ $server->isActive() ? 'آماده' : 'غیرفعال' }}
                            </span>
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                        <dt class="text-xs text-base-content/50">احراز هویت</dt>
                        <dd class="text-sm font-medium text-base-content">
                            {{ $authenticationLabel }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                        <dt class="text-xs text-base-content/50">نوع سرور</dt>
                        <dd class="text-sm font-medium text-base-content">
                            {{ $server->isCloudProvisioned() ? 'Cloud VPS' : 'VPS متصل‌شده' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                        <dt class="text-xs text-base-content/50">تاریخ ثبت</dt>
                        <dd
                            dir="ltr"
                            class="technical-value text-sm font-medium text-base-content"
                        >{{ $formatDate($server->created_at) }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Cloud metadata --}}
            <section
                class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
            >
                <div
                    class="flex items-center gap-3 border-b border-base-300 px-4 py-3.5 sm:px-5"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/55"
                    >
                        <x-icon
                            name="lucide.cloud"
                            class="!size-4.5"
                        />
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-base-content">
                            اطلاعات سرویس ابری
                        </h2>

                        <p class="mt-0.5 text-[11px] text-base-content/40">
                            مشخصات زیرساخت Cloud این سرور
                        </p>
                    </div>
                </div>

                @if($server->isCloudProvisioned())
                    <dl class="divide-y divide-base-300">
                        <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                            <dt class="text-xs text-base-content/50">ارائه‌دهنده</dt>
                            <dd class="text-sm font-medium text-base-content">
                                {{ $providerLabel }}
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                            <dt class="text-xs text-base-content/50">Region</dt>
                            <dd
                                dir="ltr"
                                class="technical-value text-sm font-medium text-base-content"
                            >{{ $server->cloud_region ?: '—' }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                            <dt class="text-xs text-base-content/50">شروع سرویس</dt>
                            <dd
                                dir="ltr"
                                class="technical-value text-sm font-medium text-base-content"
                            >{{ $formatDate($server->provisioned_at) }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                            <dt class="text-xs text-base-content/50">پایان سرویس</dt>
                            <dd
                                dir="ltr"
                                class="technical-value text-sm font-medium text-base-content"
                            >{{ $formatDate($server->expires_at) }}</dd>
                        </div>

                        <div class="flex items-start justify-between gap-4 px-4 py-3.5 sm:px-5">
                            <dt class="shrink-0 text-xs text-base-content/50">Server ID</dt>
                            <dd
                                dir="ltr"
                                class="technical-value min-w-0 break-all text-left text-xs font-medium text-base-content"
                            >{{ $server->cloud_server_id }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="flex min-h-52 items-center justify-center px-5 py-10 text-center">
                        <div class="max-w-sm">
                            <x-icon
                                name="lucide.server"
                                class="mx-auto !size-7 text-base-content/20"
                            />

                            <p class="mt-3 text-xs font-medium text-base-content/60">
                                این سرور به‌صورت دستی به xDeploy متصل شده است.
                            </p>

                            <p class="mt-1.5 text-[11px] leading-5 text-base-content/40">
                                اطلاعات Cloud Provider فقط برای سرورهایی نمایش داده می‌شود که از داخل xDeploy خریداری شده‌اند.
                            </p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-servers.workspace>
