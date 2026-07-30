<x-form
    wire:submit="{{ $submit }}"
    class="space-y-6"
>
    {{-- Server identity --}}
    <div class="space-y-2">

        <div>
            <h2 class="text-sm font-semibold text-base-content/80">
                مشخصات سرور
            </h2>

        </div>

        <x-input
            label="نام سرور"
            hint="یک نام دلخواه برای شناسایی سرور."
            hintClass="text-xs text-base-content/50"
            icon="o-server"
            placeholder="Server-01"
            wire:model="name"
            dir="ltr"
            class="text-left"
        />

        <x-input
            label="آدرس سرور"
            hint="آدرس IP یا دامنه متصل به سرور."
            hintClass="text-xs text-base-content/50"
            icon="o-globe-alt"
            placeholder="192.168.1.10 یا server.example.com"
            wire:model="host"
            dir="ltr"

            class="text-left font-mono"
        />

    </div>

    <div class="divider my-1 opacity-50"></div>

    {{-- SSH credentials --}}
    {{-- SSH credentials --}}
    <div class="space-y-5">

        <div>
            <h2 class="text-sm font-semibold text-base-content/80">
                اطلاعات اتصال SSH
            </h2>

            <p class="mt-1 text-xs text-base-content/45">
                اطلاعات موردنیاز برای برقراری اتصال امن به سرور.
            </p>
        </div>

        {{-- SSH Port --}}
        <x-input
            label="پورت SSH"
            hint="پورت پیش‌فرض SSH عدد 22 است."
            hintClass="text-xs text-base-content/50"
            icon="o-hashtag"
            type="number"
            placeholder="22"
            wire:model="port"
            dir="ltr"
            class="text-left font-mono"
        />

        {{-- Username & Password --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <x-input
                label="نام کاربری"
                hint="معمولاً root یا ubuntu."
                hintClass="text-xs text-base-content/50"
                icon="o-user"
                placeholder="root"
                wire:model="username"
                dir="ltr"
                class="text-left font-mono"
            />

            <x-password
                label="رمز عبور"
                hint="رمز عبور کاربر SSH."
                hintClass="text-xs text-base-content/50"
                icon-right="o-key"
                placeholder="**********"
                wire:model="credential"
                dir="ltr"
                class="text-left"
            />

        </div>

    </div>

    <x-slot:actions>

        <div class="flex w-full flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">

            <x-button
                label="تست اتصال"
                icon="o-signal"
                wire:click="testConnection"
                spinner="testConnection"
                class="btn-outline w-full sm:w-auto"
            />

            <x-button
                type="submit"
                :label="$button"
                icon="o-server-stack"
                spinner="{{ $submit }}"
                class="btn-primary w-full shadow-lg shadow-primary/15 transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/20 sm:w-auto"
            />

        </div>

    </x-slot:actions>

</x-form>
