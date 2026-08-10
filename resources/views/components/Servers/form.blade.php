@props([
    'submit',
    'button',
    'editing' => false,
    'requireVerifiedConnection' => false,
    'connectionVerified' => false,
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
                اطلاعات SSH موردنیاز برای اتصال xDeploy به سرور را وارد کن.
            </p>
        </div>


        {{-- Host --}}
        <x-input
            label="آدرس سرور"
            hint="آدرس IP یا دامنه سرور."
            hintClass="text-xs text-base-content/50"
            icon="lucide.globe"
            placeholder="192.168.1.10"
            wire:model.live.blur="host"
            dir="ltr"
            class="technical-value text-left"
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


        {{-- Credentials --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

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


            <x-password
                label="رمز عبور"
                :hint="$editing
                    ? 'برای حفظ رمز عبور فعلی، این فیلد را خالی بگذار.'
                    : 'رمز عبور کاربر SSH.'"
                hintClass="text-xs text-base-content/50"
                icon-right="lucide.key-round"
                placeholder="••••••••••"
                wire:model.live.blur="credential"
                dir="ltr"
                class="text-left"
            />

        </div>

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

            {{-- Test connection --}}
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


            {{-- Submit --}}
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
