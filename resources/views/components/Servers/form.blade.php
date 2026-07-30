<x-form wire:submit="{{ $submit }}">

    <x-input
        label="نام سرور (Server Name)"
        hint="یک نام دلخواه."
        hintClass="text-xs text-base-content/60"
        icon="o-server"
        placeholder="Server-01"
        wire:model="name"
        dir="ltr"
        class="text-left"
    />

    <x-input
        label="آدرس سرور (Host)"

        hintClass="text-xs text-base-content/60"
        icon="o-globe-alt"
        placeholder="192.168.1.10 یا server.example.com"
        wire:model="host"
        dir="ltr"
        class="text-left font-mono"
    />

    <x-input
        label="پورت  (SSH Port)"

        hintClass="text-xs text-base-content/60"
        icon="o-hashtag"
        type="number"
        placeholder="22"
        wire:model="port"
        dir="ltr"
        class="text-left font-mono"
    />

    <x-input
        label="نام کاربری (Username)"

        hintClass="text-xs text-base-content/60"
        icon="o-user"
        placeholder="root"
        wire:model="username"
        dir="ltr"
        class="text-left font-mono"
    />

    <x-password
        label="رمز عبور (Password)"
        hintClass="text-xs text-base-content/60"

        placeholder="••••••••••••"
        wire:model="credential"
        dir="ltr"
        class="text-left"

    />

    <x-slot:actions>

        <x-button
            label="تست اتصال"
            icon="o-signal"
            wire:click="testConnection"
            spinner="testConnection"
            class="btn-outline"
        />

        <x-button
            type="submit"
            :label="$button"
            icon="o-server-stack"
            class="btn-primary"
            spinner="{{ $submit }}"
        />

    </x-slot:actions>

</x-form>
