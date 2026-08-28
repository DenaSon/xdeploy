@props([
    'submit',
    'button',
    'editing' => false,
    'requireVerifiedConnection' => false,
    'connectionVerified' => false,
    'authenticationType' => 'password',
])

<x-form
    wire:submit="{{ $submit }}"
    class="space-y-6"
>

    {{-- Connection details --}}
    <div class="space-y-5">

        <div>
            <h2 class="text-sm font-semibold text-base-content/80">
                اطلاعات اتصال
            </h2>

            <p class="mt-1 text-xs leading-6 text-base-content/45">
                اطلاعات SSH موردنیاز برای اتصال Coreflare به سرور را وارد کنید.
            </p>
        </div>


        {{-- Host --}}
        <x-input
            label="آدرس سرور"
            :hint="$editing
                ? 'آدرس سرور پس از ثبت قابل تغییر نیست.'
                : 'آدرس IP یا دامنه سرور.'"
            hintClass="text-xs text-base-content/50"
            icon="lucide.globe"
            placeholder="192.168.1.10"
            wire:model.live.blur="host"
            :readonly="$editing"
            dir="ltr"
            class="
                technical-value
                text-left
                {{ $editing
                    ? 'cursor-not-allowed bg-base-200/50 text-base-content/55'
                    : ''
                }}
            "
        />


        {{-- Port --}}
        <x-input
            label="پورت SSH"
            hint="پورت پیش‌فرض SSH عدد 22 است."
            hintClass="text-xs text-base-content/50"
            icon="lucide.hash"
            type="number"
            placeholder="22"
            wire:model.live.blur="port"
            dir="ltr"
            class="technical-value text-left"
        />


        {{-- Authentication method --}}
        <div class="space-y-2.5">
            <div>
                <div class="text-sm font-medium text-base-content/75">
                    روش احراز هویت
                </div>

                <p class="mt-1 text-xs leading-6 text-base-content/45">
                    روشی را انتخاب کنید که SSH سرور برای ورود پشتیبانی می‌کند.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                role="radiogroup"
                aria-label="روش احراز هویت SSH"
            >
                <button
                    type="button"
                    wire:click="selectAuthenticationType('password')"
                    aria-pressed="{{ $authenticationType === 'password' ? 'true' : 'false' }}"
                    aria-label="رمز عبور — ورود SSH با رمز عبور کاربر سرور"
                    data-tip="ورود SSH با رمز عبور کاربر سرور."
                    @class([
                        'tooltip tooltip-bottom flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3.5 text-right transition',
                        'border-primary/35 bg-primary/5 ring-1 ring-primary/10' => $authenticationType === 'password',
                        'border-base-300 bg-base-100 hover:border-base-content/20 hover:bg-base-200/30' => $authenticationType !== 'password',
                    ])
                >
                    <span
                        @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-primary/10 text-primary' => $authenticationType === 'password',
                            'bg-base-200 text-base-content/45' => $authenticationType !== 'password',
                        ])
                    >
                        <x-icon
                            name="lucide.key-round"
                            class="!size-4 stroke-[1.7]"
                        />
                    </span>

                    <span class="min-w-0 text-sm font-medium text-base-content/80">
                        رمز عبور
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="selectAuthenticationType('ssh_key')"
                    aria-pressed="{{ $authenticationType === 'ssh_key' ? 'true' : 'false' }}"
                    aria-label="کلید خصوصی SSH — مناسب سرورهایی که ورود با رمز عبور غیرفعال است"
                    data-tip="مناسب سرورهایی که ورود با رمز عبور غیرفعال است."
                    @class([
                        'tooltip tooltip-bottom flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3.5 text-right transition',
                        'border-primary/35 bg-primary/5 ring-1 ring-primary/10' => $authenticationType === 'ssh_key',
                        'border-base-300 bg-base-100 hover:border-base-content/20 hover:bg-base-200/30' => $authenticationType !== 'ssh_key',
                    ])
                >
                    <span
                        @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-primary/10 text-primary' => $authenticationType === 'ssh_key',
                            'bg-base-200 text-base-content/45' => $authenticationType !== 'ssh_key',
                        ])
                    >
                        <x-icon
                            name="lucide.file-key-2"
                            class="!size-4 stroke-[1.7]"
                        />
                    </span>

                    <span class="min-w-0 text-sm font-medium text-base-content/80">
                        کلید خصوصی SSH
                    </span>
                </button>
            </div>

            @error('authenticationType')
                <p class="text-xs text-error">
                    {{ $message }}
                </p>
            @enderror
        </div>


        {{-- Credentials --}}
        <div
            @class([
                'grid grid-cols-1 gap-5',
                'sm:grid-cols-2' => $authenticationType === 'password',
            ])
        >
            <x-input
                label="نام کاربری"
                hint="معمولاً root یا ubuntu."
                hintClass="text-xs text-base-content/50"
                icon="lucide.user"
                placeholder="root"
                wire:model.live.blur="username"
                dir="ltr"
                class="technical-value text-left"
            />


            @if ($authenticationType === 'password')
                <x-password
                    label="رمز عبور"
                    :hint="$editing
                        ? 'برای حفظ رمز عبور فعلی، این فیلد را خالی بگذارید. هنگام تغییر روش احراز هویت، مقدار جدید الزامی است.'
                        : 'رمز عبور کاربر SSH.'"
                    hintClass="text-xs text-base-content/50"
                    icon-right="lucide.key-round"
                    placeholder="••••••••••"
                    wire:model.live.blur="credential"
                    dir="ltr"
                    class="text-left"
                />
            @endif
        </div>


        @if ($authenticationType === 'ssh_key')
            <div class="space-y-2">
                <x-textarea
                    label="کلید خصوصی SSH"
                    :hint="$editing
                        ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذارید. هنگام تغییر روش احراز هویت، کلید جدید الزامی است.'
                        : 'محتوای Private Key بدون passphrase را وارد کنید.'"
                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
                    wire:model.live.blur="credential"
                    rows="9"
                    dir="ltr"
                    class="font-mono text-left text-xs leading-6"
                />

                <div
                    class="flex items-start gap-2 rounded-lg bg-base-200/50 px-3 py-2.5 text-xs leading-5 text-base-content/45"
                >
                    <x-icon
                        name="lucide.info"
                        class="mt-0.5 !size-3.5 shrink-0"
                    />

                    <span>
                        فقط Private Key را وارد کنید؛ فایل public با پسوند .pub برای اتصال قابل استفاده نیست.
                    </span>
                </div>
            </div>
        @endif

    </div>


    {{-- Actions --}}
    <x-slot:actions>

        <div
            class="
                flex w-full
                flex-col-reverse gap-3
                pt-2

                sm:flex-row
                sm:justify-end
            "
        >

            <x-button
                type="button"
                :label="$connectionVerified
                    ? 'اتصال تأیید شد'
                    : 'بررسی اتصال'"
                :icon="$connectionVerified
                    ? 'lucide.circle-check'
                    : 'lucide.radio'"
                wire:click="testConnection"
                spinner="testConnection"
                @class([
                    'w-full rounded-xl sm:w-auto',
                    'btn-outline' => ! $connectionVerified,
                    'btn-success' => $connectionVerified,
                ])
            />


            <x-button
                type="submit"
                :label="$button"
                icon="lucide.server"
                spinner="{{ $submit }}"
                :disabled="$requireVerifiedConnection && ! $connectionVerified"
                class="
                    btn-primary
                    w-full
                    rounded-xl
                    font-medium
                    sm:w-auto
                "
            />

        </div>

    </x-slot:actions>

</x-form>
