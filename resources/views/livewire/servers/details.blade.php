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

        $serverOriginLabel = $server->isCloudProvisioned()
            ? 'خرید از xDeploy'
            : 'اتصال دستی';

        $formatDate = static fn ($value): string => $value !== null
            ? \App\Support\Date\JalaliDateFormatter::dateTime(
                $value,
                ' - ',
            )
            : '—';
    @endphp

    <div
        class="space-y-4"
        data-reveal-url="{{ route(
            'panel.servers.credential.reveal',
            ['server' => $server],
        ) }}"
        data-csrf-token="{{ csrf_token() }}"
        x-data="{
            password: null,
            passwordLoading: false,
            passwordError: null,
            passwordTimer: null,

            async copy(value) {
                if (!value) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(value);
                } catch (error) {
                    //
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
                        throw new Error(
                            'credential_reveal_failed',
                        );
                    }

                    const data = await response.json();

                    if (
                        typeof data.credential !== 'string'
                        || data.credential.length === 0
                    ) {
                        throw new Error(
                            'credential_missing',
                        );
                    }

                    this.password = data.credential;

                    window.clearTimeout(
                        this.passwordTimer,
                    );

                    this.passwordTimer = window.setTimeout(
                        () => this.hidePassword(),
                        30000,
                    );
                } catch (error) {
                    this.password = null;

                    this.passwordError =
                        'دریافت رمز عبور ناموفق بود. دوباره تلاش کنید.';
                } finally {
                    this.passwordLoading = false;
                }
            },

            hidePassword() {
                this.password = null;

                window.clearTimeout(
                    this.passwordTimer,
                );

                this.passwordTimer = null;
            },

            destroy() {
                this.hidePassword();
            },
        }"
    >
        {{-- Server information --}}
        <section
            class="
                card
                overflow-hidden
                border border-base-300
                bg-base-100
                shadow-none
            "
        >
            <div class="card-body gap-0 p-0">
                {{-- Header --}}
                <div
                    class="
                        flex flex-col gap-3
                        border-b border-base-300
                        px-4 py-4
                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                        sm:px-5
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="
                                flex size-10 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-primary/[0.07]
                                text-primary
                            "
                        >
                            <x-icon
                                name="lucide.server"
                                class="!size-5 stroke-[1.8]"
                            />
                        </div>

                        <div>
                            <h2
                                class="
                                    text-sm font-semibold
                                    text-base-content
                                    sm:text-base
                                "
                            >
                                مشخصات سرور
                            </h2>

                            <p
                                class="
                                    mt-0.5
                                    text-[11px]
                                    text-base-content/40
                                "
                            >
                                اطلاعات اصلی و وضعیت فعلی این سرور
                            </p>
                        </div>
                    </div>

                    @if($canRenew)
                        <x-button
                            label="تمدید سرویس"
                            icon="lucide.calendar-plus"
                            :link="route(
                                'panel.servers.renew',
                                $server,
                            )"
                            wire:navigate
                            class="
                                btn-primary btn-sm
                                self-start
                                rounded-xl
                                sm:self-auto
                            "
                        />
                    @endif
                </div>

                {{-- Primary metadata --}}
                <div
                    class="
                        grid gap-px
                        bg-base-300
                        sm:grid-cols-2
                        xl:grid-cols-4
                    "
                >
                    {{-- Name --}}
                    <div
                        class="
                            stat min-w-0
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                stat-title
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            نام سرور
                        </div>

                        <div
                            class="
                                stat-value
                                mt-1.5 truncate
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            {{ $server->name ?: 'VPS' }}
                        </div>
                    </div>

                    {{-- Status --}}
                    <div
                        class="
                            stat
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                stat-title
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            وضعیت
                        </div>

                        <div class="stat-value mt-1.5">
                            <span
                                @class([
                                    'badge badge-sm gap-1.5 font-medium',
                                    'badge-success badge-soft' => $server->isActive(),
                                    'badge-ghost' => ! $server->isActive(),
                                ])
                            >
                                <span
                                    @class([
                                        'size-1.5 rounded-full',
                                        'bg-success' => $server->isActive(),
                                        'bg-base-content/30' => ! $server->isActive(),
                                    ])
                                ></span>

                                {{ $server->isActive()
                                    ? 'آماده'
                                    : 'غیرفعال' }}
                            </span>
                        </div>
                    </div>

                    {{-- Authentication --}}
                    <div
                        class="
                            stat
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                stat-title
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            احراز هویت
                        </div>

                        <div
                            class="
                                stat-value
                                mt-1.5
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            {{ $authenticationLabel }}
                        </div>
                    </div>

                    {{-- Origin --}}
                    <div
                        class="
                            stat
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                stat-title
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            نحوه افزودن
                        </div>

                        <div
                            class="
                                stat-value
                                mt-1.5
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            {{ $serverOriginLabel }}
                        </div>
                    </div>
                </div>

                {{-- Dates --}}
                <div
                    class="
                        grid gap-px
                        border-t border-base-300
                        bg-base-300
                        sm:grid-cols-3
                    "
                >
                    <div
                        class="
                            bg-base-100
                            px-4 py-3.5
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.calendar"
                                class="!size-3.5"
                            />

                            تاریخ ثبت
                        </div>

                        <div
                            dir="ltr"
                            class="
                                technical-value
                                mt-1.5
                                text-xs font-medium
                                text-base-content
                            "
                        >
                            {{ $formatDate(
                                $server->created_at,
                            ) }}
                        </div>
                    </div>

                    <div
                        class="
                            bg-base-100
                            px-4 py-3.5
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.play"
                                class="!size-3.5"
                            />

                            شروع سرویس
                        </div>

                        <div
                            dir="ltr"
                            class="
                                technical-value
                                mt-1.5
                                text-xs font-medium
                                text-base-content
                            "
                        >
                            {{ $formatDate(
                                $server->provisioned_at,
                            ) }}
                        </div>
                    </div>

                    <div
                        class="
                            bg-base-100
                            px-4 py-3.5
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.calendar-clock"
                                class="!size-3.5"
                            />

                            پایان سرویس
                        </div>

                        <div
                            dir="ltr"
                            class="
                                technical-value
                                mt-1.5
                                text-xs font-semibold
                                text-base-content
                            "
                        >
                            {{ $formatDate(
                                $server->expires_at,
                            ) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Connection information --}}
        <section
            class="
                card
                overflow-hidden
                border border-base-300
                bg-base-100
                shadow-none
            "
        >
            <div class="card-body gap-0 p-0">
                {{-- Header --}}
                <div
                    class="
                        flex items-center gap-3
                        border-b border-base-300
                        px-4 py-3.5
                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex size-9 shrink-0
                            items-center justify-center
                            rounded-xl
                            bg-base-200
                            text-base-content/55
                        "
                    >
                        <x-icon
                            name="lucide.terminal"
                            class="!size-4.5"
                        />
                    </div>

                    <div>
                        <h2
                            class="
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            اطلاعات اتصال
                        </h2>

                        <p
                            class="
                                mt-0.5
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            اطلاعات موردنیاز برای اتصال مستقیم SSH
                        </p>
                    </div>
                </div>

                {{-- Connection grid --}}
                <div
                    class="
                        grid gap-px
                        bg-base-300
                        md:grid-cols-2
                    "
                >
                    {{-- Host --}}
                    <div
                        class="
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.network"
                                class="!size-3.5"
                            />

                            آدرس سرور
                        </div>

                        <div
                            class="
                                mt-2
                                flex min-w-0
                                items-center gap-2
                            "
                        >
                            <code
                                dir="ltr"
                                class="
                                    technical-value
                                    min-w-0 flex-1 truncate
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                {{ $server->host ?: '—' }}
                            </code>

                            @if($server->host)
                                <x-button
                                    icon="lucide.copy"
                                    data-copy-value="{{ $server->host }}"
                                    x-on:click="
                                        copy(
                                            $el.dataset.copyValue,
                                        )
                                    "
                                    class="
                                        btn-ghost btn-xs
                                        shrink-0 rounded-lg
                                    "
                                    aria-label="کپی آدرس سرور"
                                />
                            @endif
                        </div>
                    </div>

                    {{-- Port --}}
                    <div
                        class="
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.plug"
                                class="!size-3.5"
                            />

                            پورت SSH
                        </div>

                        <code
                            dir="ltr"
                            class="
                                technical-value
                                mt-2 block
                                text-sm font-semibold
                                text-base-content
                            "
                        >
                            {{ $server->port }}
                        </code>
                    </div>

                    {{-- Username --}}
                    <div
                        class="
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center gap-2
                                text-[11px]
                                text-base-content/40
                            "
                        >
                            <x-icon
                                name="lucide.user-round"
                                class="!size-3.5"
                            />

                            نام کاربری
                        </div>

                        <div
                            class="
                                mt-2
                                flex min-w-0
                                items-center gap-2
                            "
                        >
                            <code
                                dir="ltr"
                                class="
                                    technical-value
                                    min-w-0 flex-1 truncate
                                    text-sm font-semibold
                                    text-base-content
                                "
                            >
                                {{ $server->username }}
                            </code>

                            <x-button
                                icon="lucide.copy"
                                data-copy-value="{{ $server->username }}"
                                x-on:click="
                                    copy(
                                        $el.dataset.copyValue,
                                    )
                                "
                                class="
                                    btn-ghost btn-xs
                                    shrink-0 rounded-lg
                                "
                                aria-label="کپی نام کاربری"
                            />
                        </div>
                    </div>

                    {{-- Credential --}}
                    <div
                        class="
                            bg-base-100
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex items-center justify-between
                                gap-3
                            "
                        >
                            <div
                                class="
                                    flex items-center gap-2
                                    text-[11px]
                                    text-base-content/40
                                "
                            >
                                <x-icon
                                    name="lucide.key-round"
                                    class="!size-3.5"
                                />

                                {{ $authenticationLabel }}
                            </div>
                        </div>

                        @if(
                            $server->authentication_type
                            === \App\Domain\Server\Enums\AuthenticationType::Password
                        )
                            <div
                                class="
                                    mt-2
                                    flex min-w-0
                                    items-center gap-2
                                "
                            >
                                <code
                                    dir="ltr"
                                    class="
                                        technical-value
                                        min-w-0 flex-1
                                        break-all
                                        text-sm font-semibold
                                        text-base-content
                                    "
                                    x-text="
                                        password
                                            ?? '••••••••••••'
                                    "
                                ></code>

                                <x-button
                                    icon="lucide.copy"
                                    x-on:click="
                                        copy(password)
                                    "
                                    x-bind:disabled="
                                        password === null
                                    "
                                    class="
                                        btn-ghost btn-xs
                                        shrink-0 rounded-lg
                                    "
                                    aria-label="کپی رمز عبور"
                                />

                                <button
                                    type="button"
                                    x-on:click="
                                        revealPassword()
                                    "
                                    x-bind:disabled="
                                        passwordLoading
                                    "
                                    class="
                                        btn btn-ghost btn-xs
                                        shrink-0 rounded-lg
                                    "
                                >
                                    <span
                                        class="
                                            loading
                                            loading-spinner
                                            loading-xs
                                        "
                                        x-show="
                                            passwordLoading
                                        "
                                    ></span>

                                    <x-icon
                                        name="lucide.eye"
                                        class="!size-3.5"
                                        x-show="
                                            ! passwordLoading
                                            && password === null
                                        "
                                    />

                                    <x-icon
                                        name="lucide.eye-off"
                                        class="!size-3.5"
                                        x-show="
                                            ! passwordLoading
                                            && password !== null
                                        "
                                    />


                                </button>
                            </div>

                            <p
                                class="
                                    mt-1.5
                                    text-[11px]
                                    text-error
                                "
                                x-show="passwordError"
                                x-text="passwordError"
                            ></p>
                        @else
                            <p
                                class="
                                    mt-2
                                    text-xs leading-5
                                    text-base-content/45
                                "
                            >
                                اطلاعات محرمانه این روش احراز هویت نمایش داده نمی‌شود.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- SSH command --}}
                @if($sshCommand !== null)
                    <div
                        class="
                            border-t border-base-300
                            bg-base-200/20
                            px-4 py-4
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex flex-col gap-3
                                rounded-xl
                                border border-base-300
                                bg-base-100
                                px-3.5 py-3
                                sm:flex-row
                                sm:items-center
                                sm:justify-between
                            "
                        >
                            <div class="min-w-0">
                                <div
                                    class="
                                        flex items-center gap-2
                                        text-[11px]
                                        font-medium
                                        text-base-content/40
                                    "
                                >
                                    <x-icon
                                        name="lucide.square-terminal"
                                        class="!size-3.5"
                                    />

                                    دستور اتصال SSH
                                </div>

                                <code
                                    dir="ltr"
                                    class="
                                        technical-value
                                        mt-1.5 block
                                        overflow-x-auto
                                        whitespace-nowrap
                                        text-sm
                                        text-base-content
                                    "
                                >
                                    {{ $sshCommand }}
                                </code>
                            </div>

                            <x-button
                                label="کپی"
                                icon="lucide.copy"
                                data-copy-value="{{ $sshCommand }}"
                                x-on:click="
                                    copy(
                                        $el.dataset.copyValue,
                                    )
                                "
                                class="
                                    btn-outline btn-sm
                                    shrink-0 rounded-xl
                                "
                            />
                        </div>
                    </div>
                @endif
            </div>
        </section>



        {{-- Orders --}}
        @if($server->isCloudProvisioned())
            <section
                class="
                    card
                    overflow-hidden
                    border border-base-300
                    bg-base-100
                    shadow-none
                "
            >
                <div class="card-body gap-0 p-0">
                    {{-- Header --}}
                    <div
                        class="
                            flex items-center justify-between
                            gap-3
                            border-b border-base-300
                            px-4 py-3.5
                            sm:px-5
                        "
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="
                                    flex size-9 shrink-0
                                    items-center justify-center
                                    rounded-xl
                                    bg-base-200
                                    text-base-content/55
                                "
                            >
                                <x-icon
                                    name="lucide.receipt-text"
                                    class="!size-4.5"
                                />
                            </div>

                            <div>
                                <h2
                                    class="
                                        text-sm font-semibold
                                        text-base-content
                                    "
                                >
                                    سوابق سفارش‌ها
                                </h2>

                                <p
                                    class="
                                        mt-0.5
                                        text-[11px]
                                        text-base-content/40
                                    "
                                >
                                    خرید و تمدیدهای مربوط به این سرور
                                </p>
                            </div>
                        </div>

                        @if($orders->isNotEmpty())
                            <span
                                class="
                                    badge
                                    badge-ghost
                                    badge-sm
                                    font-medium
                                "
                            >
                                {{ $orders->count() }} مورد
                            </span>
                        @endif
                    </div>

                    @if($orders->isEmpty())
                        <div
                            class="
                                flex min-h-48
                                items-center justify-center
                                px-5 py-10
                                text-center
                            "
                        >
                            <div class="max-w-sm">
                                <div
                                    class="
                                        mx-auto
                                        flex size-11
                                        items-center justify-center
                                        rounded-2xl
                                        bg-base-200
                                        text-base-content/25
                                    "
                                >
                                    <x-icon
                                        name="lucide.receipt"
                                        class="!size-5"
                                    />
                                </div>

                                <p
                                    class="
                                        mt-3
                                        text-xs font-medium
                                        text-base-content/60
                                    "
                                >
                                    هنوز سفارشی ثبت نشده است
                                </p>

                                <p
                                    class="
                                        mt-1.5
                                        text-[11px] leading-5
                                        text-base-content/40
                                    "
                                >
                                    سوابق خرید و تمدید این سرور در این بخش نمایش داده می‌شود.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                <tr
                                    class="
                                            border-base-300
                                            text-[10px]
                                            text-base-content/35
                                        "
                                >
                                    <th>سفارش</th>
                                    <th>وضعیت</th>
                                    <th>مبلغ</th>
                                    <th>تاریخ</th>
                                    <th>شماره پیگیری</th>
                                </tr>
                                </thead>

                                <tbody>
                                @foreach($orders as $order)
                                    <tr
                                        class="
                                                border-base-300
                                                transition-colors
                                                hover:bg-base-200/25
                                            "
                                    >
                                        {{-- Order --}}
                                        <td>
                                            <div
                                                class="
                                                        flex items-center gap-3
                                                    "
                                            >
                                                <div
                                                    class="
                                                            flex size-8 shrink-0
                                                            items-center justify-center
                                                            rounded-lg
                                                            bg-base-200
                                                            text-base-content/45
                                                        "
                                                >
                                                    <x-icon
                                                        :name="$order['type_label'] === 'تمدید سرویس'
                                                                ? 'lucide.refresh-cw'
                                                                : 'lucide.shopping-cart'"
                                                        class="!size-3.5"
                                                    />
                                                </div>

                                                <div>
                                                    <div
                                                        class="
                                                                text-xs font-medium
                                                                text-base-content
                                                            "
                                                    >
                                                        {{ $order['type_label'] }}
                                                    </div>

                                                    <div
                                                        class="
                                                                mt-1
                                                                flex items-center gap-2
                                                                text-[10px]
                                                                text-base-content/35
                                                            "
                                                    >
                                                            <span>
                                                                #{{ $order['id'] }}
                                                            </span>

                                                        @if(
                                                            $order['period_label']
                                                            !== '—'
                                                        )
                                                            <span>
                                                                    {{ $order['period_label'] }}
                                                                </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Status --}}
                                        <td>
                                                <span
                                                    @class([
                                                        'badge badge-sm badge-soft whitespace-nowrap font-medium',

                                                        'badge-success' => $order['status_tone']
                                                            === 'success',

                                                        'badge-warning' => $order['status_tone']
                                                            === 'warning',

                                                        'badge-info' => $order['status_tone']
                                                            === 'info',

                                                        'badge-error' => $order['status_tone']
                                                            === 'error',

                                                        'badge-ghost' => $order['status_tone']
                                                            === 'neutral',
                                                    ])
                                                >
                                                    {{ $order['status_label'] }}
                                                </span>
                                        </td>

                                        {{-- Amount --}}
                                        <td>
                                            <div
                                                class="
                                                        whitespace-nowrap
                                                        text-xs font-semibold
                                                        text-base-content
                                                    "
                                            >
                                                {{ $order['amount_label'] }}
                                            </div>

                                            @if($order['verified'])
                                                <div
                                                    class="
                                                            mt-1
                                                            flex items-center gap-1
                                                            whitespace-nowrap
                                                            text-[9px]
                                                            text-success
                                                        "
                                                >
                                                    <x-icon
                                                        name="lucide.circle-check"
                                                        class="!size-3"
                                                    />

                                                    پرداخت تایید شده
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Date --}}
                                        <td
                                            dir="ltr"
                                            class="
                                                    technical-value
                                                    whitespace-nowrap
                                                    text-[11px]
                                                    text-base-content/60
                                                "
                                        >
                                            {{ $formatDate(
                                                $order['date'],
                                            ) }}
                                        </td>

                                        {{-- Reference --}}
                                        <td>
                                            @if($order['reference'] !== null)
                                                <div
                                                    class="
                                                            flex items-center
                                                            gap-1.5
                                                        "
                                                >
                                                    <code
                                                        dir="ltr"
                                                        class="
                                                                technical-value
                                                                max-w-36 truncate
                                                                text-[10px]
                                                                text-base-content/60
                                                            "
                                                    >
                                                        {{ $order['reference'] }}
                                                    </code>

                                                    <x-button
                                                        icon="lucide.copy"
                                                        data-copy-value="{{ $order['reference'] }}"
                                                        x-on:click="
                                                                copy(
                                                                    $el.dataset.copyValue,
                                                                )
                                                            "
                                                        class="
                                                                btn-ghost btn-xs
                                                                rounded-lg
                                                            "
                                                        aria-label="کپی شماره پیگیری"
                                                    />
                                                </div>
                                            @else
                                                <span
                                                    class="
                                                            text-xs
                                                            text-base-content/25
                                                        "
                                                >
                                                        —
                                                    </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>
</x-servers.workspace>
