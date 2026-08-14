<x-servers.workspace
    :server="$server"
    wire:key="server-workspace-{{ $server->getKey() }}"
>
    @php
        $authenticationLabel = match ($server->authentication_type) {
            \App\Domain\Server\Enums\AuthenticationType::Password
                => 'رمز عبور',

            \App\Domain\Server\Enums\AuthenticationType::SSHKey
                => 'کلید SSH',

            \App\Domain\Server\Enums\AuthenticationType::Agent
                => 'SSH Agent',
        };

        $serverOriginLabel = $server->isCloudProvisioned()
            ? 'VPS ابری'
            : 'VPS متصل';

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

            copied: null,
            copiedTimer: null,

            async copy(value, key) {
                if (!value) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(value);

                    this.copied = key;

                    window.clearTimeout(
                        this.copiedTimer
                    );

                    this.copiedTimer = window.setTimeout(
                        () => {
                            this.copied = null;
                        },
                        1500,
                    );
                } catch (error) {
                    this.copied = null;
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
                                'X-CSRF-TOKEN':
                                    this.$root.dataset.csrfToken,
                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },
                        },
                    );

                    if (! response.ok) {
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
                        'دریافت رمز عبور انجام نشد. دوباره تلاش کنید.';
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

                window.clearTimeout(
                    this.copiedTimer,
                );
            },
        }"
    >

        {{-- ========================================================= --}}
        {{-- Server information                                      --}}
        {{-- ========================================================= --}}

        <section
            class="
                overflow-hidden
                rounded-2xl

                border border-base-300/80
                bg-base-100
            "
        >
            {{-- Header --}}
            <header
                class="
                    flex flex-col gap-3

                    border-b border-base-300/70

                    px-4 py-4

                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    sm:px-5
                "
            >
                <div
                    class="
                        flex
                        items-center gap-3
                    "
                >
                    <span
                        class="
                            flex size-9 shrink-0
                            items-center justify-center

                            rounded-xl
                            bg-primary/10
                            text-primary
                        "
                    >
                        <x-icon
                            name="lucide.server"
                            class="!size-4 stroke-[1.8]"
                        />
                    </span>

                    <div>
                        <h2
                            class="
                                text-sm
                                font-semibold
                                text-base-content
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
                            اطلاعات پایه و وضعیت سرویس
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
                            btn-primary
                            btn-sm

                            self-start
                            rounded-xl

                            px-4
                            font-medium

                            sm:self-auto
                        "
                    />
                @endif
            </header>


            {{-- Primary metadata --}}
            <div
                class="
                    grid

                    divide-y divide-base-300/60

                    sm:grid-cols-2
                    sm:divide-y-0

                    xl:grid-cols-4
                "
            >
                {{-- Server name --}}
                <div
                    class="
                        min-w-0

                        px-4 py-3.5

                        sm:border-e
                        sm:border-base-300/60
                        sm:px-5
                    "
                >
                    <div
                        class="
                            text-[10px]
                            text-base-content/35
                        "
                    >
                        نام سرور
                    </div>

                    <div
                        class="
                            mt-1
                            truncate

                            text-sm
                            font-semibold
                            text-base-content
                        "
                    >
                        {{ $server->name ?: 'VPS' }}
                    </div>
                </div>


                {{-- Status --}}
                <div
                    class="
                        px-4 py-3.5

                        sm:px-5

                        xl:border-e
                        xl:border-base-300/60
                    "
                >
                    <div
                        class="
                            text-[10px]
                            text-base-content/35
                        "
                    >
                        وضعیت
                    </div>

                    <div class="mt-1.5">
                        <span
                            @class([
                                '
                                    inline-flex
                                    items-center gap-1.5

                                    rounded-full

                                    px-2 py-0.5

                                    text-[10px]
                                    font-medium
                                ',

                                '
                                    bg-success/10
                                    text-success
                                ' => $server->isActive(),

                                '
                                    bg-base-200
                                    text-base-content/45
                                ' => ! $server->isActive(),
                            ])
                        >
                            <span
                                @class([
                                    '
                                        size-1.5
                                        rounded-full
                                    ',

                                    'bg-success' =>
                                        $server->isActive(),

                                    'bg-base-content/25' =>
                                        ! $server->isActive(),
                                ])
                            ></span>

                            {{ $server->isActive()
                                ? 'آماده'
                                : 'غیرفعال'
                            }}
                        </span>
                    </div>
                </div>


                {{-- Authentication --}}
                <div
                    class="
                        px-4 py-3.5

                        sm:border-e
                        sm:border-base-300/60
                        sm:px-5
                    "
                >
                    <div
                        class="
                            text-[10px]
                            text-base-content/35
                        "
                    >
                        روش احراز هویت
                    </div>

                    <div
                        class="
                            mt-1

                            text-sm
                            font-medium
                            text-base-content
                        "
                    >
                        {{ $authenticationLabel }}
                    </div>
                </div>


                {{-- Server type --}}
                <div
                    class="
                        px-4 py-3.5

                        sm:px-5
                    "
                >
                    <div
                        class="
                            text-[10px]
                            text-base-content/35
                        "
                    >
                        نوع سرور
                    </div>

                    <div
                        class="
                            mt-1

                            text-sm
                            font-medium
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
                    grid

                    border-t border-base-300/60

                    bg-base-200/20

                    sm:grid-cols-3
                "
            >
                @foreach([
                    [
                        'label' => 'تاریخ ثبت',
                        'icon' => 'lucide.calendar',
                        'value' => $server->created_at,
                    ],
                    [
                        'label' => 'شروع سرویس',
                        'icon' => 'lucide.play',
                        'value' => $server->provisioned_at,
                    ],
                    [
                        'label' => 'پایان سرویس',
                        'icon' => 'lucide.calendar-clock',
                        'value' => $server->expires_at,
                    ],
                ] as $dateItem)

                    <div
                        class="
                            flex
                            items-center justify-between
                            gap-3

                            border-b border-base-300/50

                            px-4 py-3

                            last:border-b-0

                            sm:block
                            sm:border-b-0
                            sm:border-e
                            sm:last:border-e-0
                            sm:px-5
                        "
                    >
                        <div
                            class="
                                flex
                                items-center gap-1.5

                                text-[10px]
                                text-base-content/35
                            "
                        >
                            <x-icon
                                :name="$dateItem['icon']"
                                class="!size-3 stroke-[1.7]"
                            />

                            {{ $dateItem['label'] }}
                        </div>


                        <div
                            dir="ltr"
                            class="
                                technical-value

                                text-[11px]
                                font-medium
                                text-base-content/65

                                sm:mt-1
                            "
                        >
                            {{ $formatDate(
                                $dateItem['value'],
                            ) }}
                        </div>
                    </div>

                @endforeach
            </div>
        </section>



        {{-- ========================================================= --}}
        {{-- SSH connection                                           --}}
        {{-- ========================================================= --}}

        <section
            class="
                overflow-hidden
                rounded-2xl

                border border-base-300/80
                bg-base-100
            "
        >
            {{-- Header --}}
            <header
                class="
                    flex
                    items-center gap-3

                    border-b border-base-300/70

                    px-4 py-3.5

                    sm:px-5
                "
            >
                <span
                    class="
                        flex size-9 shrink-0
                        items-center justify-center

                        rounded-xl
                        bg-base-200/70

                        text-base-content/55
                    "
                >
                    <x-icon
                        name="lucide.terminal"
                        class="!size-4 stroke-[1.8]"
                    />
                </span>

                <div>
                    <h2
                        class="
                            text-sm
                            font-semibold
                            text-base-content
                        "
                    >
                        اتصال SSH
                    </h2>

                    <p
                        class="
                            mt-0.5
                            text-[11px]
                            text-base-content/40
                        "
                    >
                        اطلاعات موردنیاز برای اتصال مستقیم به سرور
                    </p>
                </div>
            </header>


            {{-- Connection grid --}}
            <div
                class="
                    grid

                    md:grid-cols-2
                "
            >
                {{-- Host --}}
                <div
                    class="
                        border-b border-base-300/60

                        px-4 py-3.5

                        md:border-e

                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-1.5

                            text-[10px]
                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.network"
                            class="!size-3 stroke-[1.7]"
                        />

                        آدرس سرور
                    </div>


                    <div
                        class="
                            mt-1.5

                            flex min-w-0
                            items-center gap-2
                        "
                    >
                        <code
                            dir="ltr"
                            class="
                                technical-value

                                min-w-0
                                flex-1
                                truncate

                                text-sm
                                font-semibold
                                text-base-content
                            "
                        >
                            {{ $server->host ?: '—' }}
                        </code>


                        @if($server->host)
                            <button
                                type="button"
                                data-copy-value="{{ $server->host }}"
                                @click="
                                    copy(
                                        $el.dataset.copyValue,
                                        'host'
                                    )
                                "
                                aria-label="کپی آدرس سرور"
                                class="
                                    btn
                                    btn-ghost
                                    btn-xs

                                    shrink-0
                                    rounded-lg
                                "
                            >
                                <x-icon
                                    name="lucide.check"
                                    x-show="copied === 'host'"
                                    x-cloak
                                    class="
                                        !size-3.5
                                        text-success
                                    "
                                />

                                <x-icon
                                    name="lucide.copy"
                                    x-show="copied !== 'host'"
                                    class="!size-3.5"
                                />
                            </button>
                        @endif
                    </div>
                </div>


                {{-- Port --}}
                <div
                    class="
                        border-b border-base-300/60

                        px-4 py-3.5

                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-1.5

                            text-[10px]
                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.plug"
                            class="!size-3 stroke-[1.7]"
                        />

                        پورت SSH
                    </div>

                    <code
                        dir="ltr"
                        class="
                            technical-value

                            mt-1.5
                            block

                            text-sm
                            font-semibold
                            text-base-content
                        "
                    >
                        {{ $server->port }}
                    </code>
                </div>


                {{-- Username --}}
                <div
                    class="
                        border-b border-base-300/60

                        px-4 py-3.5

                        md:border-b-0
                        md:border-e

                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-1.5

                            text-[10px]
                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.user-round"
                            class="!size-3 stroke-[1.7]"
                        />

                        نام کاربری
                    </div>


                    <div
                        class="
                            mt-1.5

                            flex min-w-0
                            items-center gap-2
                        "
                    >
                        <code
                            dir="ltr"
                            class="
                                technical-value

                                min-w-0
                                flex-1
                                truncate

                                text-sm
                                font-semibold
                                text-base-content
                            "
                        >
                            {{ $server->username ?: '—' }}
                        </code>


                        @if($server->username)
                            <button
                                type="button"
                                data-copy-value="{{ $server->username }}"
                                @click="
                                    copy(
                                        $el.dataset.copyValue,
                                        'username'
                                    )
                                "
                                aria-label="کپی نام کاربری"
                                class="
                                    btn
                                    btn-ghost
                                    btn-xs

                                    shrink-0
                                    rounded-lg
                                "
                            >
                                <x-icon
                                    name="lucide.check"
                                    x-show="copied === 'username'"
                                    x-cloak
                                    class="
                                        !size-3.5
                                        text-success
                                    "
                                />

                                <x-icon
                                    name="lucide.copy"
                                    x-show="copied !== 'username'"
                                    class="!size-3.5"
                                />
                            </button>
                        @endif
                    </div>
                </div>


                {{-- Credential --}}
                <div
                    class="
                        px-4 py-3.5

                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-1.5

                            text-[10px]
                            text-base-content/35
                        "
                    >
                        <x-icon
                            name="lucide.key-round"
                            class="!size-3 stroke-[1.7]"
                        />

                        {{ $authenticationLabel }}
                    </div>


                    @if(
                        $server->authentication_type
                        === \App\Domain\Server\Enums\AuthenticationType::Password
                    )
                        <div
                            class="
                                mt-1.5

                                flex
                                min-w-0
                                items-center gap-1
                            "
                        >
                            <code
                                dir="ltr"
                                x-text="
                                    password
                                        ?? '••••••••••••'
                                "
                                class="
                                    technical-value

                                    min-w-0
                                    flex-1
                                    truncate

                                    text-sm
                                    font-semibold
                                    text-base-content
                                "
                            ></code>


                            {{-- Copy password --}}
                            <button
                                type="button"
                                @click="
                                    copy(
                                        password,
                                        'password'
                                    )
                                "
                                :disabled="password === null"
                                aria-label="کپی رمز عبور"
                                class="
                                    btn
                                    btn-ghost
                                    btn-xs

                                    shrink-0
                                    rounded-lg
                                "
                            >
                                <x-icon
                                    name="lucide.check"
                                    x-show="copied === 'password'"
                                    x-cloak
                                    class="
                                        !size-3.5
                                        text-success
                                    "
                                />

                                <x-icon
                                    name="lucide.copy"
                                    x-show="copied !== 'password'"
                                    class="!size-3.5"
                                />
                            </button>


                            {{-- Reveal password --}}
                            <button
                                type="button"
                                @click="revealPassword()"
                                :disabled="passwordLoading"
                                :aria-label="
                                    password === null
                                        ? 'نمایش رمز عبور'
                                        : 'پنهان کردن رمز عبور'
                                "
                                class="
                                    btn
                                    btn-ghost
                                    btn-xs

                                    shrink-0
                                    rounded-lg
                                "
                            >
                                <span
                                    x-show="passwordLoading"
                                    class="
                                        loading
                                        loading-spinner
                                        loading-xs
                                    "
                                ></span>

                                <x-icon
                                    name="lucide.eye"
                                    x-show="
                                        ! passwordLoading
                                        && password === null
                                    "
                                    class="!size-3.5"
                                />

                                <x-icon
                                    name="lucide.eye-off"
                                    x-show="
                                        ! passwordLoading
                                        && password !== null
                                    "
                                    class="!size-3.5"
                                />
                            </button>
                        </div>


                        {{-- Auto hide notice --}}
                        <div
                            x-cloak
                            x-show="password !== null"
                            class="
                                mt-1.5

                                text-[10px]
                                text-base-content/35
                            "
                        >
                            رمز عبور پس از ۳۰ ثانیه به‌صورت خودکار پنهان می‌شود.
                        </div>


                        {{-- Password error --}}
                        <p
                            x-cloak
                            x-show="passwordError"
                            x-text="passwordError"
                            class="
                                mt-1.5

                                text-[11px]
                                text-error
                            "
                        ></p>

                    @else
                        <p
                            class="
                                mt-1.5

                                text-xs
                                leading-5
                                text-base-content/45
                            "
                        >
                            اطلاعات محرمانه این روش احراز هویت
                            قابل نمایش نیست.
                        </p>
                    @endif
                </div>
            </div>


            {{-- SSH command --}}
            @if($sshCommand !== null)
                <div
                    class="
                        border-t border-base-300/70

                        bg-base-200/20

                        p-3

                        sm:p-4
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-2

                            rounded-xl

                            border border-base-300/70
                            bg-base-100

                            px-3 py-2.5
                        "
                    >
                        <x-icon
                            name="lucide.square-terminal"
                            class="
                                !size-4
                                shrink-0

                                text-base-content/40
                                stroke-[1.7]
                            "
                        />


                        <code
                            dir="ltr"
                            class="
                                technical-value

                                min-w-0
                                flex-1

                                overflow-x-auto

                                whitespace-nowrap

                                text-xs
                                text-base-content/70

                                sm:text-sm
                            "
                        >
                            {{ $sshCommand }}
                        </code>


                        <button
                            type="button"
                            data-copy-value="{{ $sshCommand }}"
                            @click="
                                copy(
                                    $el.dataset.copyValue,
                                    'ssh'
                                )
                            "
                            aria-label="کپی دستور اتصال SSH"
                            class="
                                btn
                                btn-ghost
                                btn-sm

                                shrink-0
                                gap-1.5
                                rounded-lg
                            "
                        >
                            <x-icon
                                name="lucide.check"
                                x-show="copied === 'ssh'"
                                x-cloak
                                class="
                                    !size-4
                                    text-success
                                "
                            />

                            <x-icon
                                name="lucide.copy"
                                x-show="copied !== 'ssh'"
                                class="!size-4"
                            />

                            <span class="hidden sm:inline">
                                کپی
                            </span>
                        </button>
                    </div>
                </div>
            @endif
        </section>



        {{-- ========================================================= --}}
        {{-- Financial history                                        --}}
        {{-- ========================================================= --}}

        @if($server->isCloudProvisioned())
            <section
                class="
                    overflow-hidden
                    rounded-2xl

                    border border-base-300/80
                    bg-base-100
                "
            >
                {{-- Header --}}
                <header
                    class="
                        flex
                        items-center justify-between
                        gap-3

                        border-b border-base-300/70

                        px-4 py-3.5

                        sm:px-5
                    "
                >
                    <div
                        class="
                            flex
                            items-center gap-3
                        "
                    >
                        <span
                            class="
                                flex size-9 shrink-0
                                items-center justify-center

                                rounded-xl
                                bg-base-200/70

                                text-base-content/55
                            "
                        >
                            <x-icon
                                name="lucide.receipt-text"
                                class="!size-4 stroke-[1.8]"
                            />
                        </span>

                        <div>
                            <h2
                                class="
                                    text-sm
                                    font-semibold
                                    text-base-content
                                "
                            >
                                سوابق مالی
                            </h2>

                            <p
                                class="
                                    mt-0.5

                                    text-[11px]
                                    text-base-content/40
                                "
                            >
                                خرید و تمدیدهای ثبت‌شده برای این سرور
                            </p>
                        </div>
                    </div>


                    @if($orders->isNotEmpty())
                        <span
                            class="
                                inline-flex
                                min-w-6
                                items-center justify-center

                                rounded-full
                                bg-base-200

                                px-2 py-0.5

                                text-[10px]
                                font-medium
                                text-base-content/45
                            "
                        >
                            {{ $orders->count() }}
                        </span>
                    @endif
                </header>


                @if($orders->isEmpty())

                    {{-- Empty state --}}
                    <div
                        class="
                            flex
                            min-h-44
                            items-center justify-center

                            px-5 py-10

                            text-center
                        "
                    >
                        <div class="max-w-sm">
                            <span
                                class="
                                    mx-auto

                                    flex size-11
                                    items-center justify-center

                                    rounded-xl
                                    bg-base-200/70

                                    text-base-content/30
                                "
                            >
                                <x-icon
                                    name="lucide.receipt"
                                    class="!size-4.5 stroke-[1.7]"
                                />
                            </span>

                            <h3
                                class="
                                    mt-3

                                    text-xs
                                    font-semibold
                                    text-base-content/65
                                "
                            >
                                هنوز سابقه مالی ثبت نشده است
                            </h3>

                            <p
                                class="
                                    mt-1.5

                                    text-[11px]
                                    leading-5
                                    text-base-content/40
                                "
                            >
                                سوابق خرید و تمدید این سرور
                                پس از ثبت در این بخش نمایش داده می‌شوند.
                            </p>
                        </div>
                    </div>

                @else

                    {{-- ================================================= --}}
                    {{-- Mobile orders                                    --}}
                    {{-- ================================================= --}}

                    <div
                        class="
                            divide-y
                            divide-base-300/60

                            md:hidden
                        "
                    >
                        @foreach($orders as $order)
                            <article
                                class="
                                    px-4 py-4
                                "
                            >
                                {{-- Main row --}}
                                <div
                                    class="
                                        flex
                                        items-start justify-between
                                        gap-3
                                    "
                                >
                                    <div
                                        class="
                                            flex min-w-0
                                            items-start gap-2.5
                                        "
                                    >
                                        <span
                                            class="
                                                flex size-8 shrink-0
                                                items-center justify-center

                                                rounded-lg
                                                bg-base-200/70

                                                text-base-content/45
                                            "
                                        >
                                            <x-icon
                                                :name="$order['type_label'] === 'تمدید سرویس'
                                                    ? 'lucide.refresh-cw'
                                                    : 'lucide.shopping-cart'"
                                                class="!size-3.5 stroke-[1.7]"
                                            />
                                        </span>


                                        <div class="min-w-0">
                                            <div
                                                class="
                                                    truncate

                                                    text-xs
                                                    font-semibold
                                                    text-base-content
                                                "
                                            >
                                                {{ $order['type_label'] }}
                                            </div>

                                            <div
                                                class="
                                                    mt-1

                                                    flex flex-wrap
                                                    items-center gap-1.5

                                                    text-[10px]
                                                    text-base-content/35
                                                "
                                            >
                                                <span>
                                                    سفارش #{{ $order['id'] }}
                                                </span>

                                                @if($order['period_label'] !== '—')
                                                    <span
                                                        aria-hidden="true"
                                                        class="text-base-content/15"
                                                    >
                                                        |
                                                    </span>

                                                    <span>
                                                        {{ $order['period_label'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>


                                    <span
                                        @class([
                                            '
                                                badge
                                                badge-sm
                                                badge-soft

                                                shrink-0

                                                whitespace-nowrap
                                                font-medium
                                            ',

                                            'badge-success' =>
                                                $order['status_tone'] === 'success',

                                            'badge-warning' =>
                                                $order['status_tone'] === 'warning',

                                            'badge-info' =>
                                                $order['status_tone'] === 'info',

                                            'badge-error' =>
                                                $order['status_tone'] === 'error',

                                            'badge-ghost' =>
                                                $order['status_tone'] === 'neutral',
                                        ])
                                    >
                                        {{ $order['status_label'] }}
                                    </span>
                                </div>


                                {{-- Amount and date --}}
                                <div
                                    class="
                                        mt-3

                                        grid grid-cols-2
                                        gap-px

                                        overflow-hidden
                                        rounded-xl

                                        bg-base-300/60
                                    "
                                >
                                    {{-- Amount --}}
                                    <div
                                        class="
                                            bg-base-200/35
                                            p-3
                                        "
                                    >
                                        <div
                                            class="
                                                text-[9px]
                                                text-base-content/30
                                            "
                                        >
                                            مبلغ
                                        </div>

                                        <div
                                            class="
                                                mt-1

                                                text-xs
                                                font-semibold
                                                text-base-content
                                            "
                                        >
                                            {{ $order['amount_label'] }}
                                        </div>


                                        @if($order['verified'])
                                            <div
                                                class="
                                                    mt-1

                                                    flex
                                                    items-center gap-1

                                                    text-[9px]
                                                    text-success
                                                "
                                            >
                                                <x-icon
                                                    name="lucide.circle-check"
                                                    class="!size-3"
                                                />

                                                پرداخت تأیید شده
                                            </div>
                                        @endif
                                    </div>


                                    {{-- Date --}}
                                    <div
                                        class="
                                            bg-base-200/35
                                            p-3
                                        "
                                    >
                                        <div
                                            class="
                                                text-[9px]
                                                text-base-content/30
                                            "
                                        >
                                            تاریخ
                                        </div>

                                        <div
                                            dir="ltr"
                                            class="
                                                technical-value

                                                mt-1

                                                text-[10px]
                                                font-medium
                                                text-base-content/60
                                            "
                                        >
                                            {{ $formatDate(
                                                $order['date'],
                                            ) }}
                                        </div>
                                    </div>
                                </div>


                                {{-- Reference --}}
                                @if($order['reference'] !== null)
                                    <div
                                        class="
                                            mt-3

                                            flex
                                            items-center justify-between
                                            gap-3
                                        "
                                    >
                                        <span
                                            class="
                                                shrink-0

                                                text-[10px]
                                                text-base-content/35
                                            "
                                        >
                                            شماره پیگیری
                                        </span>


                                        <div
                                            class="
                                                flex min-w-0
                                                items-center gap-1
                                            "
                                        >
                                            <code
                                                dir="ltr"
                                                class="
                                                    technical-value

                                                    max-w-44
                                                    truncate

                                                    text-[10px]
                                                    text-base-content/60
                                                "
                                            >
                                                {{ $order['reference'] }}
                                            </code>


                                            <button
                                                type="button"
                                                data-copy-value="{{ $order['reference'] }}"
                                                @click="
                                                    copy(
                                                        $el.dataset.copyValue,
                                                        'order-{{ $order['id'] }}'
                                                    )
                                                "
                                                aria-label="کپی شماره پیگیری"
                                                class="
                                                    btn
                                                    btn-ghost
                                                    btn-xs

                                                    shrink-0
                                                    rounded-lg
                                                "
                                            >
                                                <x-icon
                                                    name="lucide.check"
                                                    x-show="copied === 'order-{{ $order['id'] }}'"
                                                    x-cloak
                                                    class="
                                                        !size-3
                                                        text-success
                                                    "
                                                />

                                                <x-icon
                                                    name="lucide.copy"
                                                    x-show="copied !== 'order-{{ $order['id'] }}'"
                                                    class="!size-3"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>


                    {{-- ================================================= --}}
                    {{-- Desktop orders                                   --}}
                    {{-- ================================================= --}}

                    <div
                        class="
                            hidden
                            overflow-x-auto

                            md:block
                        "
                    >
                        <table class="table">
                            <thead>
                            <tr
                                class="
                                        border-base-300/70

                                        text-[10px]
                                        text-base-content/35
                                    "
                            >
                                <th>
                                    سفارش
                                </th>

                                <th>
                                    وضعیت
                                </th>

                                <th>
                                    مبلغ
                                </th>

                                <th>
                                    تاریخ
                                </th>

                                <th>
                                    شماره پیگیری
                                </th>
                            </tr>
                            </thead>


                            <tbody>
                            @foreach($orders as $order)
                                <tr
                                    class="
                                            border-base-300/60

                                            transition-colors
                                            duration-150

                                            hover:bg-base-200/20
                                        "
                                >
                                    {{-- Order --}}
                                    <td>
                                        <div
                                            class="
                                                    flex
                                                    items-center gap-3
                                                "
                                        >
                                                <span
                                                    class="
                                                        flex size-8 shrink-0
                                                        items-center justify-center

                                                        rounded-lg
                                                        bg-base-200/70

                                                        text-base-content/45
                                                    "
                                                >
                                                    <x-icon
                                                        :name="$order['type_label'] === 'تمدید سرویس'
                                                            ? 'lucide.refresh-cw'
                                                            : 'lucide.shopping-cart'"
                                                        class="!size-3.5 stroke-[1.7]"
                                                    />
                                                </span>


                                            <div>
                                                <div
                                                    class="
                                                            text-xs
                                                            font-medium
                                                            text-base-content
                                                        "
                                                >
                                                    {{ $order['type_label'] }}
                                                </div>


                                                <div
                                                    class="
                                                            mt-1

                                                            flex
                                                            items-center gap-2

                                                            text-[10px]
                                                            text-base-content/35
                                                        "
                                                >
                                                        <span>
                                                            #{{ $order['id'] }}
                                                        </span>

                                                    @if($order['period_label'] !== '—')
                                                        <span
                                                            aria-hidden="true"
                                                            class="text-base-content/15"
                                                        >
                                                                |
                                                            </span>

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
                                                    '
                                                        badge
                                                        badge-sm
                                                        badge-soft

                                                        whitespace-nowrap
                                                        font-medium
                                                    ',

                                                    'badge-success' =>
                                                        $order['status_tone'] === 'success',

                                                    'badge-warning' =>
                                                        $order['status_tone'] === 'warning',

                                                    'badge-info' =>
                                                        $order['status_tone'] === 'info',

                                                    'badge-error' =>
                                                        $order['status_tone'] === 'error',

                                                    'badge-ghost' =>
                                                        $order['status_tone'] === 'neutral',
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

                                                    text-xs
                                                    font-semibold
                                                    text-base-content
                                                "
                                        >
                                            {{ $order['amount_label'] }}
                                        </div>


                                        @if($order['verified'])
                                            <div
                                                class="
                                                        mt-1

                                                        flex
                                                        items-center gap-1

                                                        whitespace-nowrap

                                                        text-[9px]
                                                        text-success
                                                    "
                                            >
                                                <x-icon
                                                    name="lucide.circle-check"
                                                    class="!size-3"
                                                />

                                                پرداخت تأیید شده
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
                                                        flex
                                                        items-center gap-1
                                                    "
                                            >
                                                <code
                                                    dir="ltr"
                                                    class="
                                                            technical-value

                                                            max-w-40
                                                            truncate

                                                            text-[10px]
                                                            text-base-content/60
                                                        "
                                                >
                                                    {{ $order['reference'] }}
                                                </code>


                                                <button
                                                    type="button"
                                                    data-copy-value="{{ $order['reference'] }}"
                                                    @click="
                                                            copy(
                                                                $el.dataset.copyValue,
                                                                'order-{{ $order['id'] }}'
                                                            )
                                                        "
                                                    aria-label="کپی شماره پیگیری"
                                                    class="
                                                            btn
                                                            btn-ghost
                                                            btn-xs

                                                            rounded-lg
                                                        "
                                                >
                                                    <x-icon
                                                        name="lucide.check"
                                                        x-show="copied === 'order-{{ $order['id'] }}'"
                                                        x-cloak
                                                        class="
                                                                !size-3
                                                                text-success
                                                            "
                                                    />

                                                    <x-icon
                                                        name="lucide.copy"
                                                        x-show="copied !== 'order-{{ $order['id'] }}'"
                                                        class="!size-3"
                                                    />
                                                </button>
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
            </section>
        @endif

    </div>
</x-servers.workspace>
